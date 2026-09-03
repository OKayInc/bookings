<?php

namespace App\Enums;

enum CouponStatus: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Used = 'used';
    case Destroyed = 'destroyed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
