<?php

namespace Tests\Feature;

use App\Domain\Availability\AvailabilityScheduleService;
use App\Domain\Bookings\BookingNoticeService;
use App\Domain\Bookings\PublicBookingAvailabilityService;
use App\Domain\Bookings\PublicBookingHoldService;
use App\Enums\AvailabilityScope;
use App\Models\AppointmentType;
use App\Models\Organization;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class BookingNoticeTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_calendar_month_notice_uses_organization_timezone_without_30_day_approximation(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-01-31 15:00:00', 'UTC'));
        [$organization, $type] = $this->typeWithNotice(1, 'month');

        $earliest = app(BookingNoticeService::class)->earliestBookableStartUtc($type);

        $this->assertSame('2026-02-28 10:00', $earliest->setTimezone($organization->timezone)->format('Y-m-d H:i'));
    }

    public function test_public_slots_hide_times_inside_notice_period(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-24 14:00:00', 'UTC')); // 10:00 Toronto
        [, $type] = $this->typeWithNotice(2, 'hour');

        $start = CarbonImmutable::parse('2026-08-24 00:00:00', 'America/Toronto')->utc();
        $end = CarbonImmutable::parse('2026-08-25 00:00:00', 'America/Toronto')->utc();
        $slots = app(PublicBookingAvailabilityService::class)->slots(
            $type,
            $start,
            $end,
            null,
            'America/Toronto',
            1,
        );

        $this->assertSame(
            ['12:00', '13:00'],
            array_map(fn ($slot) => $slot->startsAtUtc->setTimezone('America/Toronto')->format('H:i'), $slots),
        );
    }

    public function test_public_hold_cannot_bypass_notice_period(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-24 14:00:00', 'UTC')); // 10:00 Toronto
        [, $type] = $this->typeWithNotice(2, 'hour');
        $tooSoon = CarbonImmutable::parse('2026-08-24 11:00:00', 'America/Toronto')->utc();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('requires more advance notice');

        app(PublicBookingHoldService::class)->acquire(
            $type,
            $tooSoon,
            null,
            'America/Toronto',
            1,
        );
    }

    public function test_maximum_calendar_month_notice_uses_organization_timezone(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-01-31 15:00:00', 'UTC'));
        [$organization, $type] = $this->typeWithNotice(0, 'hour', 1, 'month');

        $latest = app(BookingNoticeService::class)->latestBookableStartUtc($type);

        $this->assertNotNull($latest);
        $this->assertSame('2026-02-28 10:00', $latest->setTimezone($organization->timezone)->format('Y-m-d H:i'));
    }

    public function test_zero_maximum_notice_means_no_maximum(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-24 14:00:00', 'UTC'));
        [, $type] = $this->typeWithNotice(0, 'hour', 0, 'day');
        $farFuture = CarbonImmutable::parse('2036-08-24 10:00:00', 'America/Toronto')->utc();

        $this->assertNull(app(BookingNoticeService::class)->latestBookableStartUtc($type));
        $this->assertTrue(app(BookingNoticeService::class)->permits($type, $farFuture));
    }

    public function test_public_slots_hide_times_beyond_maximum_notice(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-24 14:00:00', 'UTC')); // 10:00 Toronto
        [, $type] = $this->typeWithNotice(0, 'hour', 2, 'hour');

        $start = CarbonImmutable::parse('2026-08-24 00:00:00', 'America/Toronto')->utc();
        $end = CarbonImmutable::parse('2026-08-25 00:00:00', 'America/Toronto')->utc();
        $slots = app(PublicBookingAvailabilityService::class)->slots(
            $type,
            $start,
            $end,
            null,
            'America/Toronto',
            1,
        );

        $this->assertSame(
            ['11:00', '12:00'],
            array_map(fn ($slot) => $slot->startsAtUtc->setTimezone('America/Toronto')->format('H:i'), $slots),
        );
    }

    public function test_public_hold_cannot_bypass_maximum_notice(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-24 14:00:00', 'UTC')); // 10:00 Toronto
        [, $type] = $this->typeWithNotice(0, 'hour', 2, 'hour');
        $tooFar = CarbonImmutable::parse('2026-08-24 13:00:00', 'America/Toronto')->utc();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('cannot be booked that far in advance');

        app(PublicBookingHoldService::class)->acquire(
            $type,
            $tooFar,
            null,
            'America/Toronto',
            1,
        );
    }

    private function typeWithNotice(int $value, string $unit, int $maximumValue = 365, string $maximumUnit = 'day'): array
    {
        $organization = Organization::factory()->create([
            'timezone' => 'America/Toronto',
            'currency' => 'CAD',
        ]);

        $type = AppointmentType::create([
            'organization_id' => $organization->getKey(),
            'name' => 'Notice Session',
            'slug' => 'notice-session',
            'visibility' => 'public',
            'attendance_mode' => 'single',
            'capacity' => 1,
            'duration_mode' => 'fixed',
            'duration_unit' => 'minute',
            'duration_value' => 60,
            'start_interval_minutes' => 60,
            'booking_notice_value' => $value,
            'booking_notice_unit' => $unit,
            'maximum_booking_notice_value' => $maximumValue,
            'maximum_booking_notice_unit' => $maximumUnit,
            'buffer_before_minutes' => 0,
            'buffer_after_minutes' => 0,
            'pricing_mode' => 'free',
            'email_verification_mode' => 'none',
            'is_active' => true,
        ]);

        app(AvailabilityScheduleService::class)->save(
            $organization,
            AvailabilityScope::Organization,
            $organization,
            'America/Toronto',
            true,
            [['weekday' => 1, 'start_time' => '10:00', 'end_time' => '14:00']],
        );

        return [$organization, $type->fresh(['organization', 'resources'])];
    }
}
