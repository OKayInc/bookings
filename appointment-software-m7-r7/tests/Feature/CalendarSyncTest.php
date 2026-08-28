<?php

namespace Tests\Feature;

use App\Domain\Calendars\CalendarSyncService;
use App\Models\Appointment;
use App\Models\AppointmentType;
use App\Models\CalendarConnection;
use App\Models\ExternalCalendar;
use App\Models\Organization;
use App\Models\Resource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CalendarSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_scheduled_appointment_creates_event_on_configured_write_calendar(): void
    {
        $organization = Organization::factory()->create();
        $type = AppointmentType::create([
            'organization_id' => $organization->getKey(), 'name' => 'Synced Session', 'slug' => 'synced-session',
            'visibility' => 'public', 'attendance_mode' => 'single', 'capacity' => 1,
            'duration_mode' => 'fixed', 'duration_unit' => 'minute', 'duration_value' => 60,
            'buffer_before_minutes' => 0, 'buffer_after_minutes' => 0, 'pricing_mode' => 'free', 'is_active' => true,
        ]);
        $resource = Resource::create(['organization_id' => $organization->getKey(), 'type' => 'person', 'name' => 'Staff', 'is_active' => true, 'is_required_by_default' => true]);
        $type->resources()->attach($resource->getKey(), ['is_required' => true, 'requirement_mode' => 'inherit']);
        $connection = CalendarConnection::create([
            'organization_id' => $organization->getKey(), 'resource_id' => $resource->getKey(), 'provider' => 'google',
            'access_token' => 'token', 'refresh_token' => 'refresh', 'token_expires_at_utc' => now('UTC')->addHour(), 'status' => 'active',
        ]);
        $calendar = ExternalCalendar::create([
            'calendar_connection_id' => $connection->getKey(), 'external_id' => 'primary@example.test',
            'external_id_hash' => hash('sha256', 'primary@example.test', true), 'name' => 'Primary',
            'can_write' => true, 'is_primary' => true, 'is_active' => true,
        ]);
        $type->externalCalendars()->attach($calendar->getKey(), ['check_availability' => true, 'create_event' => true]);
        $appointment = Appointment::create([
            'organization_id' => $organization->getKey(), 'appointment_type_id' => $type->getKey(),
            'starts_at_utc' => now('UTC')->addDay(), 'ends_at_utc' => now('UTC')->addDay()->addHour(),
            'blocked_starts_at_utc' => now('UTC')->addDay(), 'blocked_ends_at_utc' => now('UTC')->addDay()->addHour(),
            'scheduling_timezone' => 'America/Toronto', 'duration_value' => 60, 'capacity' => 1, 'status' => 'scheduled',
        ]);
        $appointment->resources()->attach($resource->getKey(), ['is_required' => true]);

        Http::fake(['https://www.googleapis.com/calendar/v3/calendars/*/events' => Http::response(['id' => 'event-123', 'etag' => 'etag-1'], 200)]);
        app(CalendarSyncService::class)->syncAppointment($appointment);

        $this->assertDatabaseCount('appointment_external_events', 1);
        $this->assertSame('event-123', $appointment->externalEvents()->firstOrFail()->provider_event_id);
    }
}
