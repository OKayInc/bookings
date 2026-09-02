<?php

namespace App\Enums;

enum PaymentRuleType: string
{
    case Allowlist = 'allowlist';
    case Blocklist = 'blocklist';

    public function label(): string
    {
        return match ($this) {
            self::Allowlist => 'Allowlist — waive online prepayment',
            self::Blocklist => 'Blocklist — reject booking',
        };
    }
}
