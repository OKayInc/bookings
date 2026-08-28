<?php

namespace App\Enums;

enum EmailVerificationMode: string
{
    case None = 'none';
    case BeforeConfirmation = 'before_confirmation';
    case BeforePayment = 'before_payment';

    public function label(): string
    {
        return match ($this) {
            self::None => 'Not required',
            self::BeforeConfirmation => 'Required before booking confirmation',
            self::BeforePayment => 'Required before payment',
        };
    }
}
