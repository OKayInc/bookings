<?php

namespace Tests\Feature;

use App\Enums\MembershipRole;
use App\Enums\MembershipStatus;
use App\Models\AppointmentType;
use App\Models\CalendarConnection;
use App\Models\ExternalCalendar;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\Resource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CalendarConfigurationTest extends TestCase
{
    use RefreshDatabase;

    public function test_appointment_type_can_select_busy_calendars_and_one_write_target_per_resource(): void
    {
        [$user, $organization] = $this->ownerContext();
        $resource = Resource::create([
            'organization_id' => $organization->getKey(), 'person_id' => $user->person_id,
            'type' => 'person', 'name' => 'Photographer', 'timezone' => 'America/Toronto',
            'is_active' => true, 'is_required_by_default' => true,
        ]);
        $type = $this->type($organization);
        $type->resources()->attach($resource->getKey(), ['is_required' => true, 'requirement_mode' => 'inherit']);
        $connection = CalendarConnection::create([
            'organization_id' => $organization->getKey(), 'resource_id' => $resource->getKey(), 'provider' => 'google',
            'access_token' => 'token', 'refresh_token' => 'refresh', 'token_expires_at_utc' => now('UTC')->addHour(), 'status' => 'active',
        ]);
        $busy = $this->calendar($connection, 'busy@example.test', 'Busy calendar', false);
        $write = $this->calendar($connection, 'primary@example.test', 'Primary', true);

        $response = $this->actingAs($user)->withSession(['active_organization_uuid' => $organization->uuid])
            ->put(route('appointment-types.calendars.update', $type), [
                'check_calendars' => [$busy->uuid, $write->uuid],
                'write_calendar' => [$resource->uuid => $write->uuid],
            ]);

        $response->assertRedirect();
        $type->load('externalCalendars');
        $this->assertCount(2, $type->externalCalendars);
        $this->assertTrue((bool) $type->externalCalendars->firstWhere('uuid', $write->uuid)->pivot->create_event);
        $this->assertTrue((bool) $type->externalCalendars->firstWhere('uuid', $busy->uuid)->pivot->check_availability);
    }

    private function calendar(CalendarConnection $connection, string $externalId, string $name, bool $write): ExternalCalendar
    {
        return ExternalCalendar::create([
            'calendar_connection_id' => $connection->getKey(), 'external_id' => $externalId,
            'external_id_hash' => hash('sha256', $externalId, true), 'name' => $name,
            'can_write' => $write, 'is_primary' => $write, 'is_active' => true,
        ]);
    }

    private function type(Organization $organization): AppointmentType
    {
        return AppointmentType::create([
            'organization_id' => $organization->getKey(), 'name' => 'Calendar Session', 'slug' => 'calendar-session',
            'visibility' => 'public', 'attendance_mode' => 'single', 'capacity' => 1,
            'duration_mode' => 'fixed', 'duration_unit' => 'minute', 'duration_value' => 60,
            'start_interval_minutes' => 60, 'buffer_before_minutes' => 0, 'buffer_after_minutes' => 0,
            'pricing_mode' => 'free', 'is_active' => true,
        ]);
    }

    private function ownerContext(): array
    {
        $user = User::factory()->create(); $organization = Organization::factory()->create();
        OrganizationMembership::create([
            'organization_id' => $organization->getKey(), 'person_id' => $user->person_id,
            'role' => MembershipRole::Owner, 'status' => MembershipStatus::Active,
        ]);
        return [$user, $organization];
    }
}
