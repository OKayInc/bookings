<?php

namespace App\Enums;

enum PaymentPurpose: string
{
    case Initial = 'initial';
    case Balance = 'balance';
    case CouponPurchase = 'coupon_purchase';

    public function label(): string
    {
        return match ($this) {
            self::Initial => 'Initial payment',
            self::Balance => 'Remaining balance',
            self::CouponPurchase => 'Gift card / coupon purchase',
        };
    }
}
