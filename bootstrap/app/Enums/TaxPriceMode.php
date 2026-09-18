<?php

namespace App\Enums;

enum TaxPriceMode: string
{
    case Inclusive = 'inclusive';
    case Exclusive = 'exclusive';

    public function label(): string
    {
        return match ($this) {
            self::Inclusive => 'Taxes are included in advertised prices',
            self::Exclusive => 'Taxes are added after the subtotal',
        };
    }
}
