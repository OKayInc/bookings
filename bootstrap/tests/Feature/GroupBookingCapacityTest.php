<?php

namespace Tests\Feature;

use App\Domain\Availability\AvailabilityScheduleService;
use App\Domain\Bookings\BookingCreationService;
use App\Domain\Bookings\PublicBookingAvailabilityService;
use App\Domain\Bookings\PublicBookingHoldService;
use App\Enums\AvailabilityScope;
use App\Models\AppointmentType;
use App\Models\Organization;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class GroupBookingCapacityTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_existing_group_session_remains_joinable_until_capacity_is_exhausted(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-24 12:00 UTC'));
        $organization = Organization::factory()->create(['timezone' => 'America/Toronto']);
        $type = AppointmentType::create([
            'organization_id' => $organization->getKey(),
            'name' => 'Class', 'slug' => 'class', 'visibility' => 'public',
            'attendance_mode' => 'group', 'capacity' => 5,
            'duration_mode' => 'fixed', 'duration_unit' => 'minute', 'duration_value' => 60,
            'start_interval_minutes' => 60, 'buffer_before_minutes' => 0, 'buffer_after_minutes' => 0,
            'pricing_mode' => 'free', 'email_verification_mode' => 'none', 'is_active' => true,
        ]);
        app(AvailabilityScheduleService::class)->save($organization, AvailabilityScope::Organization, $organization, 'America/Toronto', true, [
            ['weekday' => 1, 'start_time' => '09:00', 'end_time' => '12:00'],
        ]);

        $start = CarbonImmutable::parse('2026-08-31 09:00', 'America/Toronto')->utc();
        $lease = app(PublicBookingHoldService::class)->acquire($type->fresh(['organization', 'resources']), $start, 60, 'America/Toronto', 2);
        app(BookingCreationService::class)->createFromHold($lease->token, [
            'first_name' => 'First', 'last_name' => 'Client', 'email' => 'first@example.test',
        ]);

        $type = $type->fresh(['organization', 'resources']);
        $slots = app(PublicBookingAvailabilityService::class)->slots(
            $type,
            CarbonImmutable::parse('2026-08-31 00:00', 'America/Toronto')->utc(),
            CarbonImmutable::parse('2026-09-01 00:00', 'America/Toronto')->utc(),
            60,
            'America/Toronto',
            3,
        );

        $joinable = collect($slots)->first(fn ($slot) => $slot->startsAtUtc->equalTo($start));
        $this->assertNotNull($joinable);
        $this->assertNotNull($joinable->appointment);
        $this->assertSame(3, $joinable->remainingCapacity);

        $second = app(PublicBookingHoldService::class)->acquire($type, $start, 60, 'America/Toronto', 3);
        $this->assertSame(3, $second->hold->attendee_count);

        $this->expectException(RuntimeException::class);
        app(PublicBookingHoldService::class)->acquire($type, $start, 60, 'America/Toronto', 1);
    }
}
