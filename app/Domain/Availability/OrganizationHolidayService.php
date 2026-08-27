<?php

namespace App\Domain\Availability;

use App\Enums\HolidayRuleType;
use App\Models\Organization;
use App\Models\OrganizationHoliday;
use Carbon\CarbonImmutable;

class OrganizationHolidayService
{
    /** @return list<AvailabilityInterval> */
    public function closures(
        Organization $organization,
        CarbonImmutable $rangeStartUtc,
        CarbonImmutable $rangeEndUtc,
    ): array {
        if ($rangeEndUtc->lte($rangeStartUtc)) {
            return [];
        }

        $organization->loadMissing('holidays');
        $timezone = $organization->timezone;
        $startYear = (int) $rangeStartUtc->setTimezone($timezone)->subDay()->format('Y');
        $endYear = (int) $rangeEndUtc->setTimezone($timezone)->addDay()->format('Y');
        $closures = [];

        foreach ($organization->holidays->where('is_active', true) as $holiday) {
            for ($year = $startYear; $year <= $endYear; $year++) {
                $date = $this->dateForYear($holiday, $year, $timezone);
                if ($date === null) {
                    continue;
                }

                $start = $date->startOfDay()->utc();
                $end = $date->addDay()->startOfDay()->utc();
                if ($end->lte($rangeStartUtc) || $start->gte($rangeEndUtc)) {
                    continue;
                }

                $closures[$date->format('Y-m-d')] = new AvailabilityInterval($start, $end);
            }
        }

        ksort($closures);

        return array_values($closures);
    }

    public function isClosed(
        Organization $organization,
        CarbonImmutable $startsAtUtc,
        CarbonImmutable $endsAtUtc,
    ): bool {
        $candidate = new AvailabilityInterval($startsAtUtc, $endsAtUtc);
        foreach ($this->closures($organization, $startsAtUtc->subDay(), $endsAtUtc->addDay()) as $closure) {
            if ($candidate->overlaps($closure)) {
                return true;
            }
        }

        return false;
    }

    public function dateForYear(OrganizationHoliday $holiday, int $year, string $timezone): ?CarbonImmutable
    {
        return match ($holiday->rule_type) {
            HolidayRuleType::FixedAnnual => $this->fixedDate($year, $holiday->month, $holiday->day, $timezone),
            HolidayRuleType::EasterRelative => $this->easterSunday($year, $timezone)->addDays($holiday->easter_offset_days ?? 0),
            HolidayRuleType::NthWeekday => $this->nthWeekday($year, $holiday->month, $holiday->weekday, $holiday->occurrence, $timezone),
            HolidayRuleType::OneTime => $holiday->specific_date !== null && (int) $holiday->specific_date->format('Y') === $year
                ? CarbonImmutable::parse($holiday->specific_date->format('Y-m-d'), $timezone)->startOfDay()
                : null,
        };
    }

    public function ruleDescription(OrganizationHoliday $holiday): string
    {
        $months = [1 => 'January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
        $weekdays = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
        $ordinals = [1 => 'first', 2 => 'second', 3 => 'third', 4 => 'fourth', 5 => 'fifth'];

        return match ($holiday->rule_type) {
            HolidayRuleType::FixedAnnual => ($months[$holiday->month] ?? 'Unknown month').' '.$holiday->day.' every year',
            HolidayRuleType::EasterRelative => match (true) {
                $holiday->easter_offset_days === 0 => 'Easter Sunday',
                $holiday->easter_offset_days < 0 => abs($holiday->easter_offset_days).' day(s) before Easter Sunday',
                default => $holiday->easter_offset_days.' day(s) after Easter Sunday',
            },
            HolidayRuleType::NthWeekday => ucfirst($ordinals[$holiday->occurrence] ?? ((string) $holiday->occurrence)).' '.($weekdays[$holiday->weekday] ?? 'weekday').' of '.($months[$holiday->month] ?? 'the month'),
            HolidayRuleType::OneTime => $holiday->specific_date?->format('M j, Y') ?? 'One-time date',
        };
    }

    public function nextOccurrence(OrganizationHoliday $holiday, string $timezone): ?CarbonImmutable
    {
        $today = now($timezone)->startOfDay()->toImmutable();
        $lastYear = $holiday->rule_type === HolidayRuleType::OneTime
            ? (int) ($holiday->specific_date?->format('Y') ?? $today->year)
            : $today->year + 10;

        for ($year = $today->year; $year <= $lastYear; $year++) {
            $date = $this->dateForYear($holiday, $year, $timezone);
            if ($date !== null && $date->gte($today)) {
                return $date;
            }
        }

        return null;
    }

    private function fixedDate(int $year, ?int $month, ?int $day, string $timezone): ?CarbonImmutable
    {
        if ($month === null || $day === null || ! checkdate($month, $day, $year)) {
            return null;
        }

        return CarbonImmutable::create($year, $month, $day, 0, 0, 0, $timezone);
    }

    private function nthWeekday(
        int $year,
        ?int $month,
        ?int $weekday,
        ?int $occurrence,
        string $timezone,
    ): ?CarbonImmutable {
        if ($month === null || $weekday === null || $occurrence === null || $occurrence < 1 || $occurrence > 5) {
            return null;
        }

        $first = CarbonImmutable::create($year, $month, 1, 0, 0, 0, $timezone);
        $offset = ($weekday - $first->dayOfWeek + 7) % 7;
        $date = $first->addDays($offset)->addWeeks($occurrence - 1);

        return (int) $date->format('n') === $month ? $date : null;
    }

    private function easterSunday(int $year, string $timezone): CarbonImmutable
    {
        $a = $year % 19;
        $b = intdiv($year, 100);
        $c = $year % 100;
        $d = intdiv($b, 4);
        $e = $b % 4;
        $f = intdiv($b + 8, 25);
        $g = intdiv($b - $f + 1, 3);
        $h = (19 * $a + $b - $d - $g + 15) % 30;
        $i = intdiv($c, 4);
        $k = $c % 4;
        $l = (32 + 2 * $e + 2 * $i - $h - $k) % 7;
        $m = intdiv($a + 11 * $h + 22 * $l, 451);
        $month = intdiv($h + $l - 7 * $m + 114, 31);
        $day = (($h + $l - 7 * $m + 114) % 31) + 1;

        return CarbonImmutable::create($year, $month, $day, 0, 0, 0, $timezone);
    }
}
