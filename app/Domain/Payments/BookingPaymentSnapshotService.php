<?php

namespace App\Domain\Payments;

use App\Enums\BookingNoticeUnit;
use App\Enums\PaymentCollectionMode;
use App\Enums\RetainerType;
use App\Models\AppointmentType;
use Carbon\CarbonImmutable;
use RuntimeException;

class BookingPaymentSnapshotService
{
    /** @return array<string, mixed> */
    public function snapshot(
        AppointmentType $type,
        int $priceMinor,
        CarbonImmutable $startsAtUtc,
        bool $paymentExempt,
        ?string $paymentRuleId,
    ): array {
        if ($priceMinor < 0) {
            throw new RuntimeException('A booking price cannot be negative.');
        }
        $waived = $paymentExempt && $priceMinor > 0;

        $mode = $priceMinor > 0
            ? ($type->payment_collection_mode ?? PaymentCollectionMode::Full)
            : PaymentCollectionMode::Full;
        $initial = $priceMinor;

        if ($priceMinor > 0 && $mode === PaymentCollectionMode::Retainer) {
            $initial = match ($type->retainer_type) {
                RetainerType::Fixed => min($priceMinor, max(0, (int) $type->retainer_amount_minor)),
                RetainerType::Percentage => $this->percentage($priceMinor, (int) $type->retainer_percentage_bps),
                default => throw new RuntimeException('The appointment type has an incomplete retainer configuration.'),
            };
            if ($initial <= 0) {
                throw new RuntimeException('The configured retainer must be greater than zero.');
            }
        }

        $balanceDueAt = null;
        if ($priceMinor > $initial) {
            $type->loadMissing('organization');
            $value = max(0, (int) $type->balance_due_value);
            $unit = $type->balance_due_unit instanceof BookingNoticeUnit
                ? $type->balance_due_unit
                : (BookingNoticeUnit::tryFrom((string) $type->balance_due_unit) ?? BookingNoticeUnit::Day);
            $localStart = $startsAtUtc->setTimezone($type->organization->timezone);
            $localDue = match ($unit) {
                BookingNoticeUnit::Minute => $localStart->subMinutes($value),
                BookingNoticeUnit::Hour => $localStart->subHours($value),
                BookingNoticeUnit::Day => $localStart->subDays($value),
                BookingNoticeUnit::Week => $localStart->subWeeks($value),
                BookingNoticeUnit::Month => $localStart->subMonthsNoOverflow($value),
            };
            $balanceDueAt = $localDue->utc();
        }

        return [
            'payment_collection_mode' => $mode->value,
            'initial_payment_due_minor' => $waived ? 0 : $initial,
            'balance_due_at_utc' => $balanceDueAt,
            'client_refund_percentage_bps' => min(10000, max(0, (int) $type->client_refund_percentage_bps)),
            'staff_refund_percentage_bps' => min(10000, max(0, (int) $type->staff_refund_percentage_bps)),
            'payment_exempt' => $waived,
            'payment_rule_id' => $waived ? $paymentRuleId : null,
            'payment_status' => $waived ? 'waived' : ($priceMinor === 0 ? 'paid' : 'unpaid'),
            'paid_minor' => 0,
            'refunded_minor' => 0,
        ];
    }

    private function percentage(int $amountMinor, int $basisPoints): int
    {
        $basisPoints = min(10000, max(0, $basisPoints));
        if ($amountMinor > intdiv(PHP_INT_MAX - 5000, max(1, $basisPoints))) {
            throw new RuntimeException('The retainer calculation is too large.');
        }

        return min($amountMinor, intdiv(($amountMinor * $basisPoints) + 5000, 10000));
    }
}
