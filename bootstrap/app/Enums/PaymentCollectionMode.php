<?php

namespace App\Enums;

enum PaymentCollectionMode: string
{
    case Full = 'full';
    case Retainer = 'retainer';

    public function label(): string
    {
        return match ($this) {
            self::Full => 'Collect the full amount',
            self::Retainer => 'Collect a retainer, then the balance',
        };
    }
}
