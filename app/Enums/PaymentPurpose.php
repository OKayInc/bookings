<?php

namespace App\Enums;

enum PaymentPurpose: string
{
    case Initial = 'initial';
    case Balance = 'balance';

    public function label(): string
    {
        return match ($this) {
            self::Initial => 'Initial payment',
            self::Balance => 'Remaining balance',
        };
    }
}
