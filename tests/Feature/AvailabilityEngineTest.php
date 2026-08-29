<?php

namespace Tests\Feature;

use App\Domain\Availability\AvailabilityScheduleService;
use App\Domain\Availability\AvailabilityService;
use App\Domain\Availability\BookingHoldService;
use App\Enums\AvailabilityExceptionMode;
use App\Enums\AvailabilityScope;
use App\Models\Appointment;
use App\Models\AppointmentType;
use App\Models\Organization;
use App\Models\Resource;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class AvailabilityEngineTest extends TestCase
{
    use RefreshDatabase;

    public function test_slots_are_intersection_of_type_and_resource_hours(): void
    {
        [$type, $resource] = $this->configuredType();
        $schedules = app(AvailabilityScheduleService::class);
        $organization = $type->organization;

        $schedules->save($organization, AvailabilityScope::Organization, $organization, 'America/Toronto', true, [
            ['weekday' => 1, 'start_time' => '09:00', 'end_time' => '17:00'],
        ]);
        $schedules->save($organization, AvailabilityScope::Resource, $resource, 'America/Toronto', true, [
            ['weekday' => 1, 'start_time' => '10:00', 'end_time' => '16:00'],
        ]);

        $start = CarbonImmutable::parse('2026-08-24 00:00:00', 'America/Toronto')->utc();
        $end = CarbonImmutable::parse('2026-08-25 00:00:00', 'America/Toronto')->utc();
        $slots = app(AvailabilityService::class)->slots($type->fresh(['organization', 'resources']), $start, $end, null, 'America/Toronto');

        $this->assertSame(
            ['10:00', '11:00', '12:00', '13:00', '14:00', '15:00'],
            array_map(fn ($slot) => $slot->startsAtUtc->setTimezone('America/Toronto')->format('H:i'), $slots),
        );
    }

    public function test_blackout_exception_removes_slots(): void
    {
        [$type] = $this->configuredType(assignResource: false);
        $organization = $type->organization;
        $schedules = app(AvailabilityScheduleService::class);
        $schedule = $schedules->save($organization, AvailabilityScope::Organization, $organization, 'America/Toronto', true, [
            ['weekday' => 1, 'start_time' => '09:00', 'end_time' => '14:00'],
        ]);
        $schedule->exceptions()->create([
            'starts_at_utc' => CarbonImmutable::parse('2026-08-24 11:30', 'America/Toronto')->utc(),
            'ends_at_utc' => CarbonImmutable::parse('2026-08-24 13:00', 'America/Toronto')->utc(),
            'mode' => AvailabilityExceptionMode::Unavailable,
            'timezone' => 'America/Toronto',
            'reason' => 'Lunch meeting',
        ]);

        $start = CarbonImmutable::parse('2026-08-24 00:00', 'America/Toronto')->utc();
        $end = CarbonImmutable::parse('2026-08-25 00:00', 'America/Toronto')->utc();
        $slots = app(AvailabilityService::class)->slots($type->fresh(['organization', 'resources']), $start, $end, null, 'America/Toronto');

        $this->assertSame(
            ['09:00', '10:00', '13:00'],
            array_map(fn ($slot) => $slot->startsAtUtc->setTimezone('America/Toronto')->format('H:i'), $slots),
        );
    }

    public function test_hold_blocks_second_hold_and_respects_buffers(): void
    {
        [$type] = $this->configuredType(assignResource: false, interval: 30, bufferAfter: 30);
        $organization = $type->organization;
        app(AvailabilityScheduleService::class)->save($organization, AvailabilityScope::Organization, $organization, 'America/Toronto', true, [
            ['weekday' => 1, 'start_time' => '09:00', 'end_time' => '14:00'],
        ]);

        $startAt = CarbonImmutable::parse('2026-08-24 10:00', 'America/Toronto')->utc();
        $holds = app(BookingHoldService::class);
        $lease = $holds->acquire($type->fresh(['organization', 'resources']), $startAt, null, 'America/Toronto', 10);

        $this->assertTrue($lease->hold->isActive());
        $this->expectException(RuntimeException::class);
        $holds->acquire($type->fresh(['organization', 'resources']), $startAt, null, 'America/Toronto', 10);
    }

    public function test_selected_day_slots_can_finish_in_available_hours_after_midnight(): void
    {
        [$type] = $this->configuredType(
            assignResource: false,
            interval: 15,
            durationMinutes: 480,
        );
        $organization = $type->organization;
        app(AvailabilityScheduleService::class)->save(
            $organization,
            AvailabilityScope::Organization,
            $organization,
            'America/Toronto',
            true,
            [
                ['weekday' => 1, 'start_time' => '18:00', 'end_time' => '23:59'],
                ['weekday' => 2, 'start_time' => '00:00', 'end_time' => '02:00'],
            ],
        );

        $dayStart = CarbonImmutable::parse('2026-08-24 00:00', 'America/Toronto')->utc();
        $dayEnd = CarbonImmutable::parse('2026-08-25 00:00', 'America/Toronto')->utc();
        $slots = app(AvailabilityService::class)->slots(
            $type->fresh(['organization', 'resources']),
            $dayStart,
            $dayEnd,
            null,
            'America/Toronto',
        );

        $this->assertCount(1, $slots);
        $this->assertSame('2026-08-24 18:00', $slots[0]->startsAtUtc->setTimezone('America/Toronto')->format('Y-m-d H:i'));
        $this->assertSame('2026-08-25 02:00', $slots[0]->endsAtUtc->setTimezone('America/Toronto')->format('Y-m-d H:i'));

        $lease = app(BookingHoldService::class)->acquire(
            $type->fresh(['organization', 'resources']),
            $slots[0]->startsAtUtc,
            null,
            'America/Toronto',
            10,
        );

        $this->assertSame('2026-08-25 02:00', $lease->hold->ends_at_utc->setTimezone('America/Toronto')->format('Y-m-d H:i'));
    }

    public function test_cross_midnight_slot_is_rejected_when_daily_availability_has_a_real_gap(): void
    {
        [$type] = $this->configuredType(
            assignResource: false,
            interval: 15,
            durationMinutes: 480,
        );
        $organization = $type->organization;
        app(AvailabilityScheduleService::class)->save(
            $organization,
            AvailabilityScope::Organization,
            $organization,
            'America/Toronto',
            true,
            [
                ['weekday' => 1, 'start_time' => '18:00', 'end_time' => '23:45'],
                ['weekday' => 2, 'start_time' => '00:00', 'end_time' => '03:00'],
            ],
        );

        $dayStart = CarbonImmutable::parse('2026-08-24 00:00', 'America/Toronto')->utc();
        $dayEnd = CarbonImmutable::parse('2026-08-25 00:00', 'America/Toronto')->utc();

        $this->assertSame([], app(AvailabilityService::class)->slots(
            $type->fresh(['organization', 'resources']),
            $dayStart,
            $dayEnd,
            null,
            'America/Toronto',
        ));
    }

    public function test_cross_midnight_slot_checks_conflicts_on_the_following_day(): void
    {
        [$type] = $this->configuredType(
            assignResource: false,
            interval: 15,
            durationMinutes: 480,
        );
        $organization = $type->organization;
        app(AvailabilityScheduleService::class)->save(
            $organization,
            AvailabilityScope::Organization,
            $organization,
            'America/Toronto',
            true,
            [
                ['weekday' => 1, 'start_time' => '18:00', 'end_time' => '23:59'],
                ['weekday' => 2, 'start_time' => '00:00', 'end_time' => '03:00'],
            ],
        );

        $conflictStart = CarbonImmutable::parse('2026-08-25 01:00', 'America/Toronto')->utc();
        $conflictEnd = CarbonImmutable::parse('2026-08-25 02:00', 'America/Toronto')->utc();
        Appointment::create([
            'organization_id' => $organization->getKey(),
            'appointment_type_id' => $type->getKey(),
            'starts_at_utc' => $conflictStart,
            'ends_at_utc' => $conflictEnd,
            'blocked_starts_at_utc' => $conflictStart,
            'blocked_ends_at_utc' => $conflictEnd,
            'scheduling_timezone' => 'America/Toronto',
            'duration_value' => 60,
            'capacity' => 1,
            'status' => 'scheduled',
        ]);

        $dayStart = CarbonImmutable::parse('2026-08-24 00:00', 'America/Toronto')->utc();
        $dayEnd = CarbonImmutable::parse('2026-08-25 00:00', 'America/Toronto')->utc();

        $this->assertSame([], app(AvailabilityService::class)->slots(
            $type->fresh(['organization', 'resources']),
            $dayStart,
            $dayEnd,
            null,
            'America/Toronto',
        ));
    }

    private function configuredType(
        bool $assignResource = true,
        int $interval = 60,
        int $bufferAfter = 0,
        int $durationMinutes = 60,
    ): array
    {
        $organization = Organization::factory()->create(['timezone' => 'America/Toronto']);
        $type = AppointmentType::create([
            'organization_id' => $organization->getKey(),
            'name' => 'Session',
            'slug' => 'session',
            'visibility' => 'public',
            'attendance_mode' => 'single',
            'capacity' => 1,
            'duration_mode' => 'fixed',
            'duration_unit' => 'minute',
            'duration_value' => $durationMinutes,
            'start_interval_minutes' => $interval,
            'buffer_before_minutes' => 0,
            'buffer_after_minutes' => $bufferAfter,
            'pricing_mode' => 'free',
            'is_active' => true,
        ]);

        $resource = null;
        if ($assignResource) {
            $resource = Resource::create([
                'organization_id' => $organization->getKey(),
                'type' => 'person',
                'name' => 'Resource',
                'timezone' => 'America/Toronto',
                'is_active' => true,
            ]);
            $type->resources()->attach($resource->getKey(), ['is_required' => true]);
        }

        return [$type->fresh(['organization', 'resources']), $resource];
    }
}
