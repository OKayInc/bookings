<?php

namespace App\Enums;

enum BookingStatus: string
{
    case PendingEmailVerification = 'pending_email_verification';
    case PendingContractReview = 'pending_contract_review';
    case PendingStaffConfirmation = 'pending_staff_confirmation';
    case PendingPayment = 'pending_payment';
    case Confirmed = 'confirmed';
    case Cancelled = 'cancelled';
    case Declined = 'declined';

    public function label(): string
    {
        return match ($this) {
            self::PendingEmailVerification => 'Pending email verification',
            self::PendingContractReview => 'Pending contract review',
            self::PendingStaffConfirmation => 'Pending staff confirmation',
            self::PendingPayment => 'Pending payment',
            self::Confirmed => 'Confirmed',
            self::Cancelled => 'Cancelled',
            self::Declined => 'Declined',
        };
    }

    public function occupiesCapacity(): bool
    {
        return ! in_array($this, [self::Cancelled, self::Declined], true);
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Confirmed => 'text-bg-success',
            self::Cancelled => 'text-bg-danger',
            self::Declined => 'text-bg-dark',
            self::PendingEmailVerification,
            self::PendingContractReview,
            self::PendingStaffConfirmation,
            self::PendingPayment => 'text-bg-warning',
        };
    }
}
