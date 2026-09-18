<?php

namespace App\Enums;

enum DurationUnit: string
{
    case Minute = 'minute';
    case Hour = 'hour';
    case Day = 'day';
    case Week = 'week';

    public function minutes(): int
    {
        return match ($this) {
            self::Minute => 1,
            self::Hour => 60,
            self::Day => 1440,
            self::Week => 10080,
        };
    }

    public function plural(int $value): string
    {
        return $value === 1 ? $this->value : $this->value.'s';
    }
}
