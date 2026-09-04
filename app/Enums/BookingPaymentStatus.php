<?php

namespace App\Enums;

enum BookingPaymentStatus: string
{
    case Unpaid = 'unpaid';
    case PartiallyPaid = 'partially_paid';
    case Paid = 'paid';
    case PartiallyRefunded = 'partially_refunded';
    case Refunded = 'refunded';
    case Waived = 'waived';

    public function label(): string
    {
        return match ($this) {
            self::Unpaid => 'Unpaid',
            self::PartiallyPaid => 'Partially paid',
            self::Paid => 'Paid',
            self::PartiallyRefunded => 'Partially refunded',
            self::Refunded => 'Refunded',
            self::Waived => 'Payment waived',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Unpaid => 'text-bg-danger',
            self::PartiallyPaid => 'text-bg-warning',
            self::Paid => 'text-bg-success',
            self::PartiallyRefunded => 'text-bg-info',
            self::Refunded, self::Waived => 'text-bg-secondary',
        };
    }
}
