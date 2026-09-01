<?php

namespace App\Enums;

enum TicketStatus: string
{
    case Reserved = 'reserved';
    case Issued = 'issued';
    case CheckedIn = 'checked_in';
    case Voided = 'voided';

    public function label(): string
    {
        return match ($this) {
            self::Reserved => 'Reserved',
            self::Issued => 'Valid',
            self::CheckedIn => 'Checked in',
            self::Voided => 'Voided',
        };
    }
}
