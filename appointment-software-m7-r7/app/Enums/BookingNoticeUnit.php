<?php

namespace App\Enums;

enum BookingNoticeUnit: string
{
    case Minute = 'minute';
    case Hour = 'hour';
    case Day = 'day';
    case Week = 'week';
    case Month = 'month';

    public function plural(int $value): string
    {
        return $value === 1 ? $this->value : $this->value.'s';
    }
}
