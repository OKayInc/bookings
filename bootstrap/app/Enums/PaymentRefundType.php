<?php

namespace App\Enums;

enum PaymentRefundType: string
{
    case General = 'general';
    case Deposit = 'deposit';

    public function label(): string
    {
        return match ($this) {
            self::General => 'Price refund',
            self::Deposit => 'Deposit refund',
        };
    }
}
