<?php

namespace App\Enums;

enum CouponDiscountType: string
{
    case Fixed = 'fixed';
    case Percentage = 'percentage';

    public function label(): string
    {
        return $this === self::Fixed ? 'Fixed amount' : 'Percentage';
    }
}
