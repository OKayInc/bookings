<?php

namespace App\Enums;

enum PaymentRuleMatchType: string
{
    case Email = 'email';
    case Domain = 'domain';

    public function label(): string
    {
        return match ($this) {
            self::Email => 'Exact email address',
            self::Domain => 'Email domain',
        };
    }
}
