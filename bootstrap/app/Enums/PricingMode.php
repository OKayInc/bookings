<?php

namespace App\Enums;

enum PricingMode: string
{
    case Free = 'free';
    case Fixed = 'fixed';
    case Rate = 'rate';
    case PerAttendee = 'per_attendee';

    public function label(): string
    {
        return match ($this) {
            self::Free => 'Free',
            self::Fixed => 'Fixed total',
            self::Rate => 'Duration rate',
            self::PerAttendee => 'Per attendee',
        };
    }
}
