<?php

namespace Tests\Feature;

use App\Domain\Availability\AvailabilityScheduleService;
use App\Domain\Availability\AvailabilityService;
use App\Enums\AvailabilityScope;
use App\Models\AppointmentType;
use App\Models\CalendarConnection;
use App\Models\ExternalCalendar;
use App\Models\Organization;
use App\Models\Resource;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ExternalCalendarAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_required_resource_google_busy_time_removes_matching_slot(): void
    {
        $organization = Organization::factory()->create(['timezone' => 'America/Toronto']);
        $type = AppointmentType::create([
            'organization_id' => $organization->getKey(), 'name' => 'Calendar Busy Test', 'slug' => 'calendar-busy-test',
            'visibility' => 'public', 'attendance_mode' => 'single', 'capacity' => 1,
            'duration_mode' => 'fixed', 'duration_unit' => 'minute', 'duration_value' => 60,
            'start_interval_minutes' => 60, 'buffer_before_minutes' => 0, 'buffer_after_minutes' => 0,
            'pricing_mode' => 'free', 'is_active' => true,
        ]);
        $resource = Resource::create([
            'organization_id' => $organization->getKey(), 'type' => 'person', 'name' => 'Staff',
            'timezone' => 'America/Toronto', 'is_active' => true, 'is_required_by_default' => true,
        ]);
        $type->resources()->attach($resource->getKey(), ['is_required' => true, 'requirement_mode' => 'inherit']);
        app(AvailabilityScheduleService::class)->save($organization, AvailabilityScope::Organization, $organization, 'America/Toronto', true, [
            ['weekday' => 3, 'start_time' => '09:00', 'end_time' => '12:00'],
        ]);
        $connection = CalendarConnection::create([
            'organization_id' => $organization->getKey(), 'resource_id' => $resource->getKey(), 'provider' => 'google',
            'access_token' => 'token', 'refresh_token' => 'refresh', 'token_expires_at_utc' => now('UTC')->addHour(), 'status' => 'active',
        ]);
        $calendar = ExternalCalendar::create([
            'calendar_connection_id' => $connection->getKey(), 'external_id' => 'primary@example.test',
            'external_id_hash' => hash('sha256', 'primary@example.test', true), 'name' => 'Primary',
            'can_write' => true, 'is_primary' => true, 'is_active' => true,
        ]);
        $type->externalCalendars()->attach($calendar->getKey(), ['check_availability' => true, 'create_event' => false]);

        Http::fake([
            'https://www.googleapis.com/calendar/v3/freeBusy' => Http::response([
                'calendars' => ['primary@example.test' => ['busy' => [[
                    'start' => '2026-08-26T14:00:00Z', 'end' => '2026-08-26T15:00:00Z',
                ]]]],
            ]),
        ]);

        $slots = app(AvailabilityService::class)->slots(
            $type->fresh(['organization', 'resources']),
            CarbonImmutable::parse('2026-08-26 00:00', 'America/Toronto')->utc(),
            CarbonImmutable::parse('2026-08-27 00:00', 'America/Toronto')->utc(),
            null, 'America/Toronto',
        );

        $this->assertSame(['09:00', '11:00'], array_map(
            fn ($slot) => $slot->startsAtUtc->setTimezone('America/Toronto')->format('H:i'), $slots,
        ));
    }
}
