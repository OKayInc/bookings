<?php

namespace App\Enums;

enum AvailabilityExceptionMode: string
{
    case Available = 'available';
    case Unavailable = 'unavailable';

    public function label(): string
    {
        return match ($this) {
            self::Available => 'Extra availability',
            self::Unavailable => 'Unavailable / blackout',
        };
    }
}
