<?php

namespace App\Domain\Availability;

use App\Enums\DurationMode;
use App\Enums\DurationUnit;
use App\Models\AppointmentType;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

class AppointmentDurationService
{
    public function selectedValue(AppointmentType $type, ?int $requested): int
    {
        if ($type->duration_mode === DurationMode::Fixed) {
            return (int) $type->duration_value;
        }

        if ($requested === null) {
            throw new InvalidArgumentException('A duration value is required for a variable appointment.');
        }

        $minimum = (int) $type->minimum_duration_value;
        $maximum = (int) $type->maximum_duration_value;
        $increment = (int) $type->duration_increment_value;

        if ($requested < $minimum || $requested > $maximum || (($requested - $minimum) % $increment) !== 0) {
            throw new InvalidArgumentException('The selected duration is not permitted for this appointment type.');
        }

        return $requested;
    }

    public function endAt(
        CarbonImmutable $startsAtUtc,
        AppointmentType $type,
        ?int $requestedDuration,
        string $bookingTimezone,
    ): CarbonImmutable {
        $value = $this->selectedValue($type, $requestedDuration);
        $local = $startsAtUtc->setTimezone($bookingTimezone);

        $end = match ($type->duration_unit) {
            DurationUnit::Minute => $local->addMinutes($value),
            DurationUnit::Hour => $local->addHours($value),
            DurationUnit::Day => $local->addDays($value),
            DurationUnit::Week => $local->addWeeks($value),
        };

        return $end->utc();
    }
}
