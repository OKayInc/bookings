<?php

namespace App\Domain\Availability;

use App\Enums\SeasonRecurrence;
use App\Models\AppointmentType;
use Carbon\CarbonImmutable;

class AppointmentTypeSeasonService
{
    public function isOpenAt(AppointmentType $type, CarbonImmutable $instantUtc): bool
    {
        return $this->contains($type, $instantUtc, $instantUtc->addMicrosecond());
    }

    public function contains(
        AppointmentType $type,
        CarbonImmutable $startsAtUtc,
        CarbonImmutable $endsAtUtc,
    ): bool {
        if (! $type->seasonal_availability_enabled) {
            return true;
        }

        $type->loadMissing('organization');
        if ($type->season_start_date === null
            || $type->season_end_date === null
            || $type->season_recurrence === null) {
            return false;
        }

        $timezone = $type->organization->timezone;
        $localStart = $startsAtUtc->setTimezone($timezone);
        $localEnd = $endsAtUtc->setTimezone($timezone);

        if ($type->season_recurrence === SeasonRecurrence::Once) {
            [$opensAt, $closesAt] = $this->oneTimeBounds($type, $timezone);

            return $localStart->gte($opensAt) && $localEnd->lte($closesAt);
        }

        foreach ([$localStart->year - 1, $localStart->year, $localStart->year + 1] as $year) {
            [$opensAt, $closesAt] = $this->yearlyBounds($type, $timezone, $year);
            if ($localStart->gte($opensAt) && $localEnd->lte($closesAt)) {
                return true;
            }
        }

        return false;
    }

    /** @return array{CarbonImmutable, CarbonImmutable} */
    private function oneTimeBounds(AppointmentType $type, string $timezone): array
    {
        $opensAt = CarbonImmutable::parse($type->season_start_date->format('Y-m-d'), $timezone)->startOfDay();
        $closesAt = CarbonImmutable::parse($type->season_end_date->format('Y-m-d'), $timezone)->addDay()->startOfDay();

        return [$opensAt, $closesAt];
    }

    /** @return array{CarbonImmutable, CarbonImmutable} */
    private function yearlyBounds(AppointmentType $type, string $timezone, int $startYear): array
    {
        $startMonth = (int) $type->season_start_date->format('n');
        $startDay = (int) $type->season_start_date->format('j');
        $endMonth = (int) $type->season_end_date->format('n');
        $endDay = (int) $type->season_end_date->format('j');
        $crossesNewYear = $type->season_end_date->year > $type->season_start_date->year;

        $opensAt = $this->localDate($startYear, $startMonth, $startDay, $timezone);
        $endDate = $this->localDate(
            $startYear + ($crossesNewYear ? 1 : 0),
            $endMonth,
            $endDay,
            $timezone,
        );

        return [$opensAt, $endDate->addDay()->startOfDay()];
    }

    private function localDate(int $year, int $month, int $day, string $timezone): CarbonImmutable
    {
        $first = CarbonImmutable::create($year, $month, 1, 0, 0, 0, $timezone);

        return $first->day(min($day, $first->daysInMonth))->startOfDay();
    }
}
