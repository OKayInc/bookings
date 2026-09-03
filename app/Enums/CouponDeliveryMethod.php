<?php

namespace App\Enums;

enum CouponDeliveryMethod: string
{
    case Print = 'print';
    case EmailLink = 'email_link';
    case EmailQr = 'email_qr';

    public function label(): string
    {
        return match ($this) {
            self::Print => 'Print it',
            self::EmailLink => 'Email a protected link',
            self::EmailQr => 'Email a QR code',
        };
    }
}
