<?php

namespace App\Enums;

enum HolidayRuleType: string
{
    case FixedAnnual = 'fixed_annual';
    case EasterRelative = 'easter_relative';
    case NthWeekday = 'nth_weekday';
    case OneTime = 'one_time';

    public function label(): string
    {
        return match ($this) {
            self::FixedAnnual => 'Annual fixed date',
            self::EasterRelative => 'Relative to Easter Sunday',
            self::NthWeekday => 'Nth weekday of a month',
            self::OneTime => 'One-time date',
        };
    }
}
