<?php

namespace Tests\Unit;

use App\Domain\Availability\AppointmentDurationService;
use App\Models\AppointmentType;
use Carbon\CarbonImmutable;
use InvalidArgumentException;
use Tests\TestCase;

class AppointmentDurationServiceTest extends TestCase
{
    public function test_day_duration_uses_calendar_timezone_across_dst(): void
    {
        $type = new AppointmentType([
            'duration_mode' => 'fixed',
            'duration_unit' => 'day',
            'duration_value' => 1,
        ]);

        $start = CarbonImmutable::parse('2026-11-01 00:30', 'America/Toronto')->utc();
        $end = app(AppointmentDurationService::class)->endAt($start, $type, null, 'America/Toronto');

        $this->assertSame('2026-11-02 00:30', $end->setTimezone('America/Toronto')->format('Y-m-d H:i'));
    }

    public function test_variable_duration_rejects_value_off_increment(): void
    {
        $type = new AppointmentType([
            'duration_mode' => 'variable',
            'duration_unit' => 'minute',
            'duration_value' => 1,
            'minimum_duration_value' => 30,
            'maximum_duration_value' => 120,
            'duration_increment_value' => 15,
        ]);

        $this->expectException(InvalidArgumentException::class);
        app(AppointmentDurationService::class)->selectedValue($type, 50);
    }
}
