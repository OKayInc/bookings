<?php

namespace Tests\Feature;

use App\Domain\Bookings\BookingWorkflowService;
use App\Enums\MembershipRole;
use App\Enums\MembershipStatus;
use App\Models\Appointment;
use App\Models\AppointmentType;
use App\Models\Booking;
use App\Models\Organization;
use App\Models\OrganizationContact;
use App\Models\OrganizationMembership;
use App\Models\Resource;
use App\Models\ResourceConfirmation;
use App\Models\User;
use App\Notifications\StaffConfirmationRequestEmail;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class StaffConfirmationTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_required_employee_can_accept_in_backend_and_booking_confirms(): void
    {
        Notification::fake();
        [$organization, $type, $appointment, $booking, $employee] = $this->bookingWithRequiredEmployee();

        app(BookingWorkflowService::class)->refreshStatus($booking->load(['appointmentType', 'contractSubmissions', 'appointment.resources.person']));
        $booking->refresh();
        $this->assertSame('pending_staff_confirmation', $booking->status->value);
        $confirmation = ResourceConfirmation::firstOrFail();
        $this->assertTrue($confirmation->is_required);
        Notification::assertSentOnDemand(StaffConfirmationRequestEmail::class);

        $this->actingAs($employee)
            ->withSession(['active_organization_uuid' => $organization->uuid])
            ->post(route('bookings.confirmations.respond', [$booking, $confirmation]), [
                'action' => 'accepted',
                'response_note' => 'Available.',
            ])
            ->assertRedirect();

        $this->assertSame('accepted', $confirmation->fresh()->status->value);
        $this->assertSame('confirmed', $booking->fresh()->status->value);
    }

    public function test_private_email_link_can_accept_without_backend_login(): void
    {
        Notification::fake();
        [$organization, $type, $appointment, $booking] = $this->bookingWithRequiredEmployee();
        app(BookingWorkflowService::class)->refreshStatus($booking->load(['appointmentType', 'contractSubmissions', 'appointment.resources.person']));
        $confirmation = ResourceConfirmation::firstOrFail();
        $token = 'known-confirmation-token';
        $confirmation->update(['response_token_hash' => hash('sha256', $token, true)]);

        $this->get(route('public.staff-confirmations.show', [$confirmation, $token]))
            ->assertOk()
            ->assertSee('Accept appointment');
        $this->post(route('public.staff-confirmations.respond', [$confirmation, $token]), ['action' => 'accepted'])
            ->assertOk()
            ->assertSee('Response saved');

        $this->assertSame('confirmed', $booking->fresh()->status->value);
    }

    public function test_required_employee_decline_declines_booking(): void
    {
        Notification::fake();
        [$organization, $type, $appointment, $booking, $employee] = $this->bookingWithRequiredEmployee();
        app(BookingWorkflowService::class)->refreshStatus($booking->load(['appointmentType', 'contractSubmissions', 'appointment.resources.person']));
        $confirmation = ResourceConfirmation::firstOrFail();

        $this->actingAs($employee)
            ->withSession(['active_organization_uuid' => $organization->uuid])
            ->post(route('bookings.confirmations.respond', [$booking, $confirmation]), [
                'action' => 'declined',
                'response_note' => 'Unavailable.',
            ])
            ->assertRedirect();

        $this->assertSame('declined', $booking->fresh()->status->value);
        $this->assertSame('declined', $confirmation->fresh()->status->value);
    }

    public function test_booking_uses_snapshotted_confirmation_requirement_after_type_changes(): void
    {
        Notification::fake();
        [$organization, $type, $appointment, $booking] = $this->bookingWithRequiredEmployee();
        $type->update(['requires_resource_confirmation' => false]);

        app(BookingWorkflowService::class)->refreshStatus($booking->load(['appointmentType', 'contractSubmissions', 'appointment.resources.person']));

        $this->assertSame('pending_staff_confirmation', $booking->fresh()->status->value);
        $this->assertTrue($booking->fresh()->requires_resource_confirmation);
        $this->assertSame(1, ResourceConfirmation::count());
    }

    public function test_optional_employee_decline_does_not_block_required_acceptance(): void
    {
        Notification::fake();
        [$organization, $type, $appointment, $booking, $requiredUser] = $this->bookingWithRequiredEmployee();
        $optionalUser = User::factory()->create();
        OrganizationMembership::create([
            'organization_id' => $organization->getKey(), 'person_id' => $optionalUser->person_id,
            'role' => MembershipRole::Employee, 'status' => MembershipStatus::Active,
        ]);
        $optional = Resource::create([
            'organization_id' => $organization->getKey(), 'person_id' => $optionalUser->person_id,
            'type' => 'person', 'name' => 'Optional assistant', 'timezone' => 'America/Toronto',
            'is_active' => true, 'is_required_by_default' => false,
        ]);
        $appointment->resources()->attach($optional->getKey(), ['is_required' => false]);

        app(BookingWorkflowService::class)->refreshStatus($booking->load(['appointmentType', 'contractSubmissions', 'appointment.resources.person']));
        $required = ResourceConfirmation::where('is_required', true)->firstOrFail();
        $optionalConfirmation = ResourceConfirmation::where('is_required', false)->firstOrFail();

        $this->actingAs($optionalUser)->withSession(['active_organization_uuid' => $organization->uuid])
            ->post(route('bookings.confirmations.respond', [$booking, $optionalConfirmation]), ['action' => 'declined'])
            ->assertRedirect();
        $this->assertSame('pending_staff_confirmation', $booking->fresh()->status->value);

        $this->actingAs($requiredUser)->withSession(['active_organization_uuid' => $organization->uuid])
            ->post(route('bookings.confirmations.respond', [$booking, $required]), ['action' => 'accepted'])
            ->assertRedirect();
        $this->assertSame('confirmed', $booking->fresh()->status->value);
    }

    private function bookingWithRequiredEmployee(): array
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-25 12:00 UTC'));
        $organization = Organization::factory()->create(['timezone' => 'America/Toronto']);
        $employee = User::factory()->create();
        OrganizationMembership::create([
            'organization_id' => $organization->getKey(), 'person_id' => $employee->person_id,
            'role' => MembershipRole::Employee, 'status' => MembershipStatus::Active,
        ]);
        $type = AppointmentType::create([
            'organization_id' => $organization->getKey(), 'name' => 'Confirmation Session', 'slug' => 'confirmation-session',
            'visibility' => 'public', 'attendance_mode' => 'single', 'capacity' => 1, 'duration_mode' => 'fixed',
            'duration_unit' => 'minute', 'duration_value' => 60, 'start_interval_minutes' => 60,
            'buffer_before_minutes' => 0, 'buffer_after_minutes' => 0, 'pricing_mode' => 'free',
            'email_verification_mode' => 'none', 'requires_resource_confirmation' => true, 'is_active' => true,
        ]);
        $resource = Resource::create([
            'organization_id' => $organization->getKey(), 'person_id' => $employee->person_id,
            'type' => 'person', 'name' => 'Photographer', 'timezone' => 'America/Toronto',
            'is_active' => true, 'is_required_by_default' => true,
        ]);
        $appointment = Appointment::create([
            'organization_id' => $organization->getKey(), 'appointment_type_id' => $type->getKey(),
            'starts_at_utc' => now('UTC')->addDays(2), 'ends_at_utc' => now('UTC')->addDays(2)->addHour(),
            'blocked_starts_at_utc' => now('UTC')->addDays(2), 'blocked_ends_at_utc' => now('UTC')->addDays(2)->addHour(),
            'scheduling_timezone' => 'America/Toronto', 'duration_value' => 60, 'capacity' => 1, 'status' => 'scheduled',
        ]);
        $appointment->resources()->attach($resource->getKey(), ['is_required' => true]);
        $contact = OrganizationContact::create([
            'organization_id' => $organization->getKey(), 'first_name' => 'Client', 'last_name' => 'One', 'email' => 'client@example.test',
        ]);
        $booking = Booking::create([
            'organization_id' => $organization->getKey(), 'appointment_id' => $appointment->getKey(),
            'appointment_type_id' => $type->getKey(), 'organization_contact_id' => $contact->getKey(),
            'reference' => 'CONFIRM00001', 'status' => 'pending_staff_confirmation', 'attendee_count' => 1,
            'booking_timezone' => 'America/Toronto', 'base_price_minor' => 0, 'price_minor' => 0, 'currency' => 'CAD',
            'first_name' => 'Client', 'last_name' => 'One', 'email' => 'client@example.test', 'email_normalized' => 'client@example.test',
            'manage_token_hash' => random_bytes(32), 'requires_resource_confirmation' => true,
        ]);

        return [$organization, $type, $appointment, $booking, $employee];
    }
}
