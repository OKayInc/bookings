<?php

namespace App\Enums;

enum AttendeePricingMode: string
{
    case Flat = 'flat';
    case Absolute = 'absolute';
    case Accumulative = 'accumulative';

    public function label(): string
    {
        return match ($this) {
            self::Flat => 'Same rate for every attendee',
            self::Absolute => 'Absolute ranges — matching rate for all attendees',
            self::Accumulative => 'Accumulative ranges — each portion at its own rate',
        };
    }
}
