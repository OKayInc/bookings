<?php

namespace App\Domain\Availability;

use App\Enums\HolidayRuleType;

class OrganizationHolidayPresetCatalog
{
    /** @return array<string, array<string, mixed>> */
    public function all(): array
    {
        return [
            'new_years_day' => $this->fixed("New Year's Day", 1, 1),
            'family_day_ontario' => $this->nth('Family Day (Ontario)', 2, 1, 3),
            'good_friday' => $this->easter('Good Friday', -2),
            'easter_sunday' => $this->easter('Easter Sunday', 0),
            'easter_monday' => $this->easter('Easter Monday', 1),
            'canada_day' => $this->fixed('Canada Day', 7, 1),
            'civic_holiday_ontario' => $this->nth('Civic Holiday (Ontario)', 8, 1, 1),
            'labour_day_canada' => $this->nth('Labour Day (Canada)', 9, 1, 1),
            'thanksgiving_canada' => $this->nth('Thanksgiving (Canada)', 10, 1, 2),
            'remembrance_day' => $this->fixed('Remembrance Day', 11, 11),
            'christmas_day' => $this->fixed('Christmas Day', 12, 25),
            'boxing_day' => $this->fixed('Boxing Day', 12, 26),
        ];
    }

    /** @return array<string, mixed>|null */
    public function get(string $key): ?array
    {
        return $this->all()[$key] ?? null;
    }

    /** @return array<string, mixed> */
    private function fixed(string $name, int $month, int $day): array
    {
        return [
            'name' => $name,
            'rule_type' => HolidayRuleType::FixedAnnual,
            'month' => $month,
            'day' => $day,
        ];
    }

    /** @return array<string, mixed> */
    private function easter(string $name, int $offset): array
    {
        return [
            'name' => $name,
            'rule_type' => HolidayRuleType::EasterRelative,
            'easter_offset_days' => $offset,
        ];
    }

    /** @return array<string, mixed> */
    private function nth(string $name, int $month, int $weekday, int $occurrence): array
    {
        return [
            'name' => $name,
            'rule_type' => HolidayRuleType::NthWeekday,
            'month' => $month,
            'weekday' => $weekday,
            'occurrence' => $occurrence,
        ];
    }
}
