<?php

namespace App\Enums;

enum ResourceConfirmationStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Declined = 'declined';
    case Superseded = 'superseded';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Accepted => 'Accepted',
            self::Declined => 'Declined',
            self::Superseded => 'Not needed',
        };
    }
}
