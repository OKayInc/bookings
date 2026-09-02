<?php

namespace App\Enums;

enum PricingAdjustmentType: string
{
    case None = 'none';
    case Fixed = 'fixed';
    case Percentage = 'percentage';
    case Rate = 'rate';

    public function label(): string
    {
        return match ($this) {
            self::None => 'Free / no extra charge',
            self::Fixed => 'Fixed charge',
            self::Percentage => 'Percentage',
            self::Rate => 'Answer × rate',
        };
    }
}
