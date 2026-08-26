<?php

namespace Tests\Feature;

use App\Domain\Availability\AvailabilityScheduleService;
use App\Domain\Bookings\BookingScheduleProposalService;
use App\Enums\AvailabilityScope;
use App\Enums\BookingHoldStatus;
use App\Enums\MembershipRole;
use App\Enums\MembershipStatus;
use App\Enums\ScheduleProposalStatus;
use App\Models\Appointment;
use App\Models\AppointmentType;
use App\Models\Booking;
use App\Models\BookingScheduleProposal;
use App\Models\Organization;
use App\Models\OrganizationContact;
use App\Models\OrganizationMembership;
use App\Models\Resource;
use App\Models\ResourceConfirmation;
use App\Models\User;
use App\Notifications\BookingScheduleProposalEmail;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class StaffScheduleProposalTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_staff_proposal_reserves_alternative_time_and_client_acceptance_reschedules_without_using_client_quota(): void
    {
        Notification::fake();
        [$booking, $staff] = $this->fixture();
        $service = app(BookingScheduleProposalService::class);
        $proposedStart = CarbonImmutable::parse('2026-08-26 15:00', 'America/Toronto')->utc();

        $created = $service->create($booking, $staff->person, $proposedStart, 'America/Toronto', 24, 'Staff unavailable.', 'I can see you tomorrow instead.');
        $proposal = $created['proposal'];

        $this->assertSame(ScheduleProposalStatus::Pending, $proposal->status);
        $this->assertSame(BookingHoldStatus::Active, $proposal->hold->status);
        Notification::assertSentOnDemand(BookingScheduleProposalEmail::class);

        $oldAppointmentId = $booking->appointment_id;
        $updated = $service->accept($proposal);

        $this->assertFalse(hash_equals($oldAppointmentId, $updated->appointment_id));
        $this->assertTrue($updated->appointment->starts_at_utc->equalTo($proposedStart));
        $this->assertSame(0, $updated->reschedule_count, 'A staff-initiated proposal must not consume the client reschedule quota.');
        $this->assertSame('pending_payment', $updated->status->value);
        $this->assertSame(ScheduleProposalStatus::Accepted, $proposal->fresh()->status);
        $this->assertSame(BookingHoldStatus::Consumed, $proposal->hold->fresh()->status);
        $this->assertFalse($proposal->fresh()->warning_active);
        $this->assertFalse($updated->reschedules()->firstOrFail()->client_initiated);
    }

    public function test_accepting_staff_proposal_resets_required_confirmation_for_the_new_time(): void
    {
        Notification::fake();
        [$booking, $staff] = $this->fixture();
        $resource = Resource::create([
            'organization_id' => $booking->organization_id,
            'person_id' => $staff->person_id,
            'type' => 'person',
            'name' => 'Required photographer',
            'timezone' => 'America/Toronto',
            'is_active' => true,
            'is_required_by_default' => true,
        ]);
        $booking->appointmentType->resources()->attach($resource->getKey(), ['is_required' => true, 'requirement_mode' => 'required']);
        $booking->appointment->resources()->attach($resource->getKey(), ['is_required' => true]);
        $booking->update(['requires_resource_confirmation' => true]);
        ResourceConfirmation::create([
            'organization_id' => $booking->organization_id,
            'booking_id' => $booking->getKey(),
            'appointment_id' => $booking->appointment_id,
            'resource_id' => $resource->getKey(),
            'person_id' => $staff->person_id,
            'is_required' => true,
            'status' => 'accepted',
            'response_token_hash' => random_bytes(32),
            'responded_at_utc' => now('UTC'),
        ]);

        $created = app(BookingScheduleProposalService::class)->create(
            $booking->fresh(['appointment', 'appointmentType.organization', 'appointmentType.resources']),
            $staff->person,
            CarbonImmutable::parse('2026-08-26 12:00', 'America/Toronto')->utc(),
            'America/Toronto',
            24,
        );
        $updated = app(BookingScheduleProposalService::class)->accept($created['proposal']);

        $this->assertSame('pending_staff_confirmation', $updated->fresh()->status->value);
        $confirmations = ResourceConfirmation::where('booking_id', $updated->getKey())->get();
        $this->assertCount(1, $confirmations);
        $this->assertSame('pending', $confirmations->first()->status->value);
        $this->assertTrue(hash_equals($updated->appointment_id, $confirmations->first()->appointment_id));
    }

    public function test_client_can_keep_original_time_and_warning_remains_active(): void
    {
        Notification::fake();
        [$booking, $staff] = $this->fixture();
        $originalStart = $booking->appointment->starts_at_utc;
        $created = app(BookingScheduleProposalService::class)->create(
            $booking,
            $staff->person,
            CarbonImmutable::parse('2026-08-26 16:00', 'America/Toronto')->utc(),
            'America/Toronto',
            24,
            'Staff conflict.',
        );

        $proposal = app(BookingScheduleProposalService::class)->keepOriginal($created['proposal']);

        $this->assertSame(ScheduleProposalStatus::KeptOriginal, $proposal->status);
        $this->assertTrue($proposal->warning_active);
        $this->assertSame(BookingHoldStatus::Released, $proposal->hold->fresh()->status);
        $this->assertTrue($booking->fresh()->appointment->starts_at_utc->equalTo($originalStart));
        $this->assertSame('pending_payment', $booking->fresh()->status->value);
    }

    public function test_client_can_cancel_due_to_staff_proposal_even_when_normal_cancellation_is_disabled(): void
    {
        Notification::fake();
        [$booking, $staff] = $this->fixture();
        $booking->update(['cancellation_allowed' => false]);
        $created = app(BookingScheduleProposalService::class)->create(
            $booking->fresh(['appointment', 'appointmentType.organization']),
            $staff->person,
            CarbonImmutable::parse('2026-08-26 17:00', 'America/Toronto')->utc(),
            'America/Toronto',
            24,
        );

        app(BookingScheduleProposalService::class)->cancelBooking($created['proposal'], 'The alternative does not work for me.');
        $booking->refresh();

        $this->assertSame('cancelled', $booking->status->value);
        $this->assertSame('staff_schedule_change', $booking->cancellation_origin);
        $this->assertSame($booking->getKey(), $created['proposal']->booking_id);
        $this->assertSame(ScheduleProposalStatus::Cancelled, $created['proposal']->fresh()->status);
        $this->assertSame(BookingHoldStatus::Released, $created['proposal']->hold->fresh()->status);
    }

    public function test_expired_proposal_releases_hold_and_leaves_staff_warning_on_original_booking(): void
    {
        Notification::fake();
        [$booking, $staff] = $this->fixture();
        $created = app(BookingScheduleProposalService::class)->create(
            $booking,
            $staff->person,
            CarbonImmutable::parse('2026-08-26 14:00', 'America/Toronto')->utc(),
            'America/Toronto',
            1,
            'Staff conflict.',
        );
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-25 13:01 UTC'));

        $this->assertSame(1, app(BookingScheduleProposalService::class)->expire());
        $proposal = $created['proposal']->fresh();

        $this->assertSame(ScheduleProposalStatus::Expired, $proposal->status);
        $this->assertTrue($proposal->warning_active);
        $this->assertSame(BookingHoldStatus::Released, $proposal->hold->fresh()->status);
        $this->assertSame('pending_payment', $booking->fresh()->status->value);
    }

    public function test_group_session_schedule_proposal_uses_the_proposal_expiry_for_its_capacity_hold(): void
    {
        Notification::fake();
        [$booking, $staff] = $this->fixture();
        $booking->appointmentType->update(['attendance_mode' => 'group', 'capacity' => 10]);
        $booking->appointment->update(['capacity' => 10]);

        $proposedStart = CarbonImmutable::parse('2026-08-26 15:00', 'America/Toronto')->utc();
        Appointment::create([
            'organization_id' => $booking->organization_id,
            'appointment_type_id' => $booking->appointment_type_id,
            'starts_at_utc' => $proposedStart,
            'ends_at_utc' => $proposedStart->addHour(),
            'blocked_starts_at_utc' => $proposedStart,
            'blocked_ends_at_utc' => $proposedStart->addHour(),
            'scheduling_timezone' => 'America/Toronto',
            'duration_value' => 60,
            'capacity' => 10,
            'status' => 'scheduled',
        ]);

        $created = app(BookingScheduleProposalService::class)->create(
            $booking->fresh(['appointment', 'appointmentType.organization']),
            $staff->person,
            $proposedStart,
            'America/Toronto',
            2,
        );

        $hold = $created['proposal']->hold->fresh();
        $this->assertSame(BookingHoldStatus::Active, $hold->status);
        $this->assertTrue($hold->expires_at_utc->equalTo(CarbonImmutable::parse('2026-08-25 14:00 UTC')));
    }

    public function test_manage_page_exposes_three_choices_for_pending_staff_proposal(): void
    {
        Notification::fake();
        [$booking, $staff] = $this->fixture();
        $manageToken = 'known-manage-token';
        $booking->update(['manage_token_hash' => hash('sha256', $manageToken, true)]);
        app(BookingScheduleProposalService::class)->create(
            $booking->fresh(['appointment', 'appointmentType.organization']),
            $staff->person,
            CarbonImmutable::parse('2026-08-26 13:00', 'America/Toronto')->utc(),
            'America/Toronto',
            24,
        );

        $this->get(route('public.bookings.manage', [$booking, $manageToken]))
            ->assertOk()
            ->assertSee('Accept proposed time')
            ->assertSee('Keep original time')
            ->assertSee('Cancel booking');
    }

    private function fixture(): array
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-25 12:00 UTC'));
        $organization = Organization::factory()->create(['timezone' => 'America/Toronto', 'currency' => 'CAD']);
        $staff = User::factory()->create();
        OrganizationMembership::create([
            'organization_id' => $organization->getKey(),
            'person_id' => $staff->person_id,
            'role' => MembershipRole::Owner,
            'status' => MembershipStatus::Active,
        ]);
        $type = AppointmentType::create([
            'organization_id' => $organization->getKey(),
            'name' => 'Proposal Session', 'slug' => 'proposal-session', 'visibility' => 'public',
            'attendance_mode' => 'single', 'capacity' => 1, 'duration_mode' => 'fixed',
            'duration_unit' => 'minute', 'duration_value' => 60, 'start_interval_minutes' => 60,
            'buffer_before_minutes' => 0, 'buffer_after_minutes' => 0, 'pricing_mode' => 'fixed',
            'fixed_price_minor' => 10000, 'email_verification_mode' => 'none', 'is_active' => true,
        ]);
        app(AvailabilityScheduleService::class)->save(
            $organization,
            AvailabilityScope::Organization,
            $organization,
            'America/Toronto',
            true,
            [['weekday' => 3, 'start_time' => '09:00', 'end_time' => '18:00']],
        );
        $currentStart = CarbonImmutable::parse('2026-08-27 15:00', 'America/Toronto')->utc();
        $appointment = Appointment::create([
            'organization_id' => $organization->getKey(), 'appointment_type_id' => $type->getKey(),
            'starts_at_utc' => $currentStart, 'ends_at_utc' => $currentStart->addHour(),
            'blocked_starts_at_utc' => $currentStart, 'blocked_ends_at_utc' => $currentStart->addHour(),
            'scheduling_timezone' => 'America/Toronto', 'duration_value' => 60, 'capacity' => 1, 'status' => 'scheduled',
        ]);
        $contact = OrganizationContact::create([
            'organization_id' => $organization->getKey(), 'first_name' => 'Client', 'last_name' => 'Example',
            'email' => 'client@example.test',
        ]);
        $booking = Booking::create([
            'organization_id' => $organization->getKey(), 'appointment_id' => $appointment->getKey(),
            'appointment_type_id' => $type->getKey(), 'organization_contact_id' => $contact->getKey(),
            'reference' => 'PROPOSAL0001', 'status' => 'pending_payment', 'attendee_count' => 1,
            'booking_timezone' => 'America/Toronto', 'base_price_minor' => 10000, 'price_minor' => 10000,
            'currency' => 'CAD', 'first_name' => 'Client', 'last_name' => 'Example', 'email' => 'client@example.test',
            'email_normalized' => 'client@example.test', 'manage_token_hash' => random_bytes(32),
            'requires_resource_confirmation' => false, 'cancellation_allowed' => true,
            'cancellation_notice_value' => 24, 'cancellation_notice_unit' => 'hour',
            'rescheduling_allowed' => true, 'rescheduling_notice_value' => 24, 'rescheduling_notice_unit' => 'hour',
            'rescheduling_max_count' => 1, 'reschedule_count' => 0,
        ]);

        return [$booking->fresh(['appointment', 'appointmentType.organization']), $staff];
    }
}
