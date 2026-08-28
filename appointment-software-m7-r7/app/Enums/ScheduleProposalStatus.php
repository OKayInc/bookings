<?php

namespace App\Enums;

enum ScheduleProposalStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case KeptOriginal = 'kept_original';
    case Cancelled = 'cancelled';
    case Expired = 'expired';
    case Withdrawn = 'withdrawn';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending client response',
            self::Accepted => 'Accepted',
            self::KeptOriginal => 'Client kept original time',
            self::Cancelled => 'Client cancelled',
            self::Expired => 'Expired',
            self::Withdrawn => 'Withdrawn by staff',
        };
    }

    public function isOpen(): bool
    {
        return $this === self::Pending;
    }
}
