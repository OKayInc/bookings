<?php

namespace Tests\Feature;

use App\Domain\Availability\AvailabilityScheduleService;
use App\Domain\Availability\AvailabilityService;
use App\Enums\AvailabilityScope;
use App\Models\Appointment;
use App\Models\AppointmentType;
use App\Models\BookingHold;
use App\Models\CalendarConnection;
use App\Models\ExternalCalendar;
use App\Models\Organization;
use App\Models\Resource;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ReplacementAvailabilityBatchTest extends TestCase
{
    // Browsing snapshots intentionally do not run inside a transaction.
    use DatabaseMigrations;

    public function test_batch_matches_fresh_checks_with_buffers_holds_calendars_and_schedule_exceptions(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-25 08:00 UTC'));
        config(['resource-availability-cache.seconds' => 0, 'calendars.busy_cache_seconds' => 0]);
        Http::preventStrayRequests();
        $organization = Organization::factory()->create(['timezone' => 'UTC']);
        $type = AppointmentType::create([
            'organization_id' => $organization->getKey(), 'name' => 'Session', 'slug' => 'session',
            'visibility' => 'public', 'attendance_mode' => 'single', 'capacity' => 1,
            'duration_mode' => 'fixed', 'duration_unit' => 'minute', 'duration_value' => 30,
            'start_interval_minutes' => 15, 'buffer_before_minutes' => 15, 'buffer_after_minutes' => 15,
            'pricing_mode' => 'free', 'is_active' => true,
        ]);
        $hours = [['weekday' => 1, 'start_time' => '09:00', 'end_time' => '13:00']];
        $schedules = app(AvailabilityScheduleService::class);
        $schedules->save($organization, AvailabilityScope::Organization, $organization, 'UTC', true, $hours);
        $resources = [];
        foreach (['first', 'second', 'disabled'] as $name) {
            $resource = Resource::create(['organization_id' => $organization->getKey(), 'type' => 'person',
                'name' => $name, 'is_active' => true, 'is_required_by_default' => true]);
            $resources[] = $resource;
            $type->resources()->attach($resource->getKey(), ['is_required' => true,
                'requirement_mode' => 'replacement', 'replacement_group' => 'Host']);
            if ($name === 'disabled') {
                // An inactive custom schedule must not fall back to organization hours.
                $schedules->save($organization, AvailabilityScope::Resource, $resource, 'UTC', false, $hours);
                continue;
            }
            $connection = CalendarConnection::create([
                'organization_id' => $organization->getKey(), 'resource_id' => $resource->getKey(), 'provider' => 'google',
                'access_token' => 'token', 'refresh_token' => 'refresh', 'token_expires_at_utc' => now('UTC')->addDay(), 'status' => 'active',
            ]);
            $calendar = ExternalCalendar::create(['calendar_connection_id' => $connection->getKey(),
                'external_id' => $name, 'external_id_hash' => hash('sha256', $name, true), 'name' => $name,
                'can_write' => true, 'is_active' => true]);
            $type->externalCalendars()->attach($calendar->getKey(), ['check_availability' => true, 'create_event' => false]);
        }
        $schedule = $schedules->save($organization, AvailabilityScope::Resource, $resources[0], 'UTC', true, $hours);
        $schedule->exceptions()->create(['starts_at_utc' => '2026-10-26 12:00:00',
            'ends_at_utc' => '2026-10-26 13:00:00', 'mode' => 'unavailable', 'timezone' => 'UTC']);
        $appointment = Appointment::create([
            'organization_id' => $organization->getKey(), 'appointment_type_id' => $type->getKey(),
            'starts_at_utc' => '2026-10-26 09:00:00', 'ends_at_utc' => '2026-10-26 10:00:00',
            'blocked_starts_at_utc' => '2026-10-26 08:45:00', 'blocked_ends_at_utc' => '2026-10-26 10:15:00',
            'scheduling_timezone' => 'UTC', 'duration_value' => 60, 'capacity' => 1, 'status' => 'scheduled',
        ]);
        $appointment->resources()->attach($resources[1]->getKey(), ['is_required' => true]);
        $hold = BookingHold::create([
            'organization_id' => $organization->getKey(), 'appointment_type_id' => $type->getKey(),
            'token_hash' => hash('sha256', 'batch-test', true), 'starts_at_utc' => '2026-10-26 12:00:00',
            'ends_at_utc' => '2026-10-26 13:00:00', 'blocked_starts_at_utc' => '2026-10-26 12:00:00',
            'blocked_ends_at_utc' => '2026-10-26 13:00:00', 'booking_timezone' => 'UTC', 'duration_value' => 60,
            'attendee_count' => 1, 'status' => 'active', 'expires_at_utc' => now('UTC')->addMinutes(10),
        ]);
        $hold->resources()->attach($resources[1]->getKey(), ['is_required' => true]);
        $from = CarbonImmutable::parse('2026-10-26 00:00 UTC');
        $read = function (bool $batch) use ($type, $from): array {
            config(['availability.batch_resource_checks' => $batch]);
            Http::fake(['https://www.googleapis.com/calendar/v3/freeBusy' => function ($request) {
                $responses = [];
                foreach ($request['items'] as $item) {
                    $responses[$item['id']] = ['busy' => $item['id'] === 'first'
                        ? [['start' => '2026-10-26T09:00:00Z', 'end' => '2026-10-26T11:00:00Z']] : []];
                }
                return Http::response(['calendars' => $responses]);
            }]);
            DB::enableQueryLog();
            DB::flushQueryLog();
            $slots = app(AvailabilityService::class)->slots($type->fresh(), $from, $from->addDay());
            $queries = count(DB::getQueryLog());
            DB::disableQueryLog();
            return [array_map(fn ($slot) => $slot->startsAtUtc->format('H:i'), $slots), $queries, Http::recorded()->count()];
        };
        [$legacy, $legacyQueries, $legacyCalls] = $read(false);
        [$batch, $batchQueries, $batchCalls] = $read(true);
        $this->assertSame(['10:30', '10:45', '11:00', '11:15', '11:30'], $batch);
        $this->assertSame($legacy, $batch);
        $this->assertLessThan($legacyQueries / 2, $batchQueries);
        $this->assertGreaterThan(2, $legacyCalls);
        $this->assertSame(2, $batchCalls);

        // A new conflict is visible on the next read; no snapshot survives a call.
        $appointment->update(['blocked_ends_at_utc' => '2026-10-26 13:00:00']);
        [$changed] = $read(true);
        $this->assertSame(['11:15', '11:30'], $changed);

        // Transactions still use the authoritative per-slot path.
        DB::beginTransaction();
        try {
            [$transactionSlots, , $transactionCalls] = $read(true);
            $this->assertSame($changed, $transactionSlots);
            $this->assertGreaterThan(2, $transactionCalls);
        } finally {
            DB::rollBack();
        }

        // Quantity-managed replacements can use spare units despite overlapping allocations.
        $resources[1]->update(['type' => 'equipment', 'quantity_enabled' => true, 'inventory_quantity' => 2]);
        [$quantityLegacy] = $read(false);
        [$quantityBatch] = $read(true);
        $this->assertSame($quantityLegacy, $quantityBatch);
        $this->assertContains('09:00', $quantityBatch);
        $this->assertNotContains('12:30', $quantityBatch);
        $hold->update(['expires_at_utc' => now('UTC')->subSecond()]);
        [$expiredHold] = $read(true);
        $this->assertContains('12:30', $expiredHold);
    }
}
