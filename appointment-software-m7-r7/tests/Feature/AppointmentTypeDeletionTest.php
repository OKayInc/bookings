<?php

namespace Tests\Feature;

use App\Enums\AvailabilityScope;
use App\Enums\MembershipRole;
use App\Enums\MembershipStatus;
use App\Models\Appointment;
use App\Models\AppointmentType;
use App\Models\AvailabilitySchedule;
use App\Models\Booking;
use App\Models\Organization;
use App\Models\OrganizationContact;
use App\Models\OrganizationMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AppointmentTypeDeletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_unused_appointment_type_can_be_deleted(): void
    {
        [$user, $organization] = $this->ownerContext();
        $type = $this->appointmentType($organization);

        AvailabilitySchedule::create([
            'organization_id' => $organization->getKey(),
            'scope_type' => AvailabilityScope::AppointmentType->value,
            'scope_id' => $type->getKey(),
            'timezone' => 'America/Toronto',
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)
            ->withSession(['active_organization_uuid' => $organization->uuid])
            ->delete(route('appointment-types.destroy', $type));

        $response->assertRedirect(route('appointment-types.index'));
        $response->assertSessionHas('success');
        $this->assertFalse(AppointmentType::whereUuid($type->uuid)->exists());
        $this->assertSame(0, AvailabilitySchedule::query()
            ->where('scope_type', AvailabilityScope::AppointmentType->value)
            ->where('scope_id', $type->getKey())
            ->count());
    }

    public function test_appointment_type_with_any_booking_cannot_be_deleted_and_can_be_disabled(): void
    {
        [$user, $organization] = $this->ownerContext();
        $type = $this->appointmentType($organization);
        $contact = OrganizationContact::create([
            'organization_id' => $organization->getKey(),
            'first_name' => 'Client',
            'last_name' => 'Example',
            'email' => 'client@example.test',
        ]);
        $appointment = Appointment::create([
            'organization_id' => $organization->getKey(),
            'appointment_type_id' => $type->getKey(),
            'starts_at_utc' => now('UTC')->addDay(),
            'ends_at_utc' => now('UTC')->addDay()->addHour(),
            'blocked_starts_at_utc' => now('UTC')->addDay(),
            'blocked_ends_at_utc' => now('UTC')->addDay()->addHour(),
            'scheduling_timezone' => 'America/Toronto',
            'duration_value' => 60,
            'capacity' => 1,
            'status' => 'scheduled',
        ]);
        Booking::create([
            'organization_id' => $organization->getKey(),
            'appointment_id' => $appointment->getKey(),
            'appointment_type_id' => $type->getKey(),
            'organization_contact_id' => $contact->getKey(),
            'reference' => 'BOOKING00001',
            'status' => 'cancelled',
            'attendee_count' => 1,
            'booking_timezone' => 'America/Toronto',
            'price_minor' => 0,
            'currency' => 'CAD',
            'first_name' => 'Client',
            'last_name' => 'Example',
            'email' => 'client@example.test',
            'email_normalized' => 'client@example.test',
            'manage_token_hash' => hash('sha256', 'manage-token', true),
        ]);

        $delete = $this->actingAs($user)
            ->withSession(['active_organization_uuid' => $organization->uuid])
            ->delete(route('appointment-types.destroy', $type));

        $delete->assertRedirect(route('appointment-types.index'));
        $delete->assertSessionHas('error');
        $this->assertTrue(AppointmentType::whereUuid($type->uuid)->exists());

        $disable = $this->actingAs($user)
            ->withSession(['active_organization_uuid' => $organization->uuid])
            ->patch(route('appointment-types.disable', $type));

        $disable->assertRedirect(route('appointment-types.index'));
        $type->refresh();
        $this->assertFalse($type->is_active);
        $this->assertSame(1, $type->bookings()->count());
    }

    public function test_index_shows_delete_only_for_unused_type_and_disable_for_used_type(): void
    {
        [$user, $organization] = $this->ownerContext();
        $unused = $this->appointmentType($organization, 'Unused Session', 'unused-session');
        $used = $this->appointmentType($organization, 'Used Session', 'used-session');

        $contact = OrganizationContact::create([
            'organization_id' => $organization->getKey(),
            'first_name' => 'Client',
            'last_name' => 'Example',
            'email' => 'client2@example.test',
        ]);
        $appointment = Appointment::create([
            'organization_id' => $organization->getKey(),
            'appointment_type_id' => $used->getKey(),
            'starts_at_utc' => now('UTC')->addDays(2),
            'ends_at_utc' => now('UTC')->addDays(2)->addHour(),
            'blocked_starts_at_utc' => now('UTC')->addDays(2),
            'blocked_ends_at_utc' => now('UTC')->addDays(2)->addHour(),
            'scheduling_timezone' => 'America/Toronto',
            'duration_value' => 60,
            'capacity' => 1,
            'status' => 'scheduled',
        ]);
        Booking::create([
            'organization_id' => $organization->getKey(),
            'appointment_id' => $appointment->getKey(),
            'appointment_type_id' => $used->getKey(),
            'organization_contact_id' => $contact->getKey(),
            'reference' => 'BOOKING00002',
            'status' => 'confirmed',
            'attendee_count' => 1,
            'booking_timezone' => 'America/Toronto',
            'price_minor' => 0,
            'currency' => 'CAD',
            'first_name' => 'Client',
            'last_name' => 'Example',
            'email' => 'client2@example.test',
            'email_normalized' => 'client2@example.test',
            'manage_token_hash' => hash('sha256', 'manage-token-2', true),
        ]);

        $response = $this->actingAs($user)
            ->withSession(['active_organization_uuid' => $organization->uuid])
            ->get(route('appointment-types.index'));

        $response->assertOk();
        $response->assertSee(route('appointment-types.disable', $used), false);

        $html = $response->getContent();
        $unusedDestroyUrl = preg_quote(route('appointment-types.destroy', $unused), '/');
        $usedDestroyUrl = preg_quote(route('appointment-types.destroy', $used), '/');

        $this->assertMatchesRegularExpression(
            '/<form[^>]+action=[\"\']'.$unusedDestroyUrl.'[\"\'][^>]*>.*?<input[^>]+name=[\"\']_method[\"\'][^>]+value=[\"\']DELETE[\"\']/s',
            $html,
        );

        $this->assertDoesNotMatchRegularExpression(
            '/<form[^>]+action=[\"\']'.$usedDestroyUrl.'[\"\'][^>]*>.*?<input[^>]+name=[\"\']_method[\"\'][^>]+value=[\"\']DELETE[\"\']/s',
            $html,
        );
    }

    private function appointmentType(Organization $organization, string $name = 'Session', string $slug = 'session'): AppointmentType
    {
        return AppointmentType::create([
            'organization_id' => $organization->getKey(),
            'name' => $name,
            'slug' => $slug,
            'visibility' => 'public',
            'attendance_mode' => 'single',
            'capacity' => 1,
            'duration_mode' => 'fixed',
            'duration_unit' => 'minute',
            'duration_value' => 60,
            'buffer_before_minutes' => 0,
            'buffer_after_minutes' => 0,
            'pricing_mode' => 'free',
            'is_active' => true,
        ]);
    }

    private function ownerContext(): array
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create(['currency' => 'CAD']);

        OrganizationMembership::create([
            'organization_id' => $organization->getKey(),
            'person_id' => $user->person_id,
            'role' => MembershipRole::Owner,
            'status' => MembershipStatus::Active,
        ]);

        return [$user, $organization];
    }
}
