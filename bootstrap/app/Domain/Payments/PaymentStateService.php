<?php

namespace App\Domain\Payments;

use App\Domain\Bookings\BookingWorkflowService;
use App\Enums\BookingPaymentStatus;
use App\Enums\PaymentRefundStatus;
use App\Enums\PaymentRefundType;
use App\Enums\PaymentTransactionStatus;
use App\Models\Booking;
use App\Models\PaymentTransaction;
use App\Models\Coupon;
use App\Enums\CouponStatus;
use App\Domain\Coupons\CouponIssuanceService;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class PaymentStateService
{
    public function __construct(private readonly BookingWorkflowService $workflow, private readonly CouponIssuanceService $coupons)
    {
    }

    /** @param array<string,mixed> $payload */
    public function completeCoupon(PaymentTransaction $payment, string $captureId, int $amountMinor, string $currency, array $payload): Coupon
    {
        if (trim($captureId) === '' || $amountMinor !== (int) $payment->amount_minor || strtoupper($currency) !== strtoupper($payment->currency)) {
            throw new RuntimeException('The provider coupon payment does not match the immutable purchase request.');
        }
        $coupon = DB::transaction(function () use ($payment, $captureId, $payload): Coupon {
            $coupon = Coupon::query()->whereKey($payment->coupon_id)->lockForUpdate()->firstOrFail();
            $locked = PaymentTransaction::query()->whereKey($payment->getKey())->lockForUpdate()->firstOrFail();
            if ($locked->status !== PaymentTransactionStatus::Succeeded) {
                $locked->update([
                    'status' => PaymentTransactionStatus::Succeeded->value,
                    'provider_capture_id' => $captureId,
                    'provider_payload' => $payload,
                    'failure_message' => null,
                    'checkout_url' => null,
                    'completed_at_utc' => now('UTC'),
                ]);
                if ($coupon->status === CouponStatus::Pending) {
                    $coupon->update(['status' => CouponStatus::Active->value, 'activated_at_utc' => now('UTC')]);
                }
            }
            return $coupon->fresh(['organization', 'appointmentTypes']);
        }, 3);
        if ($coupon->status === CouponStatus::Active) {
            $this->coupons->deliver($coupon);
        }

        return $coupon->fresh();
    }

    /** @param array<string,mixed> $payload */
    public function complete(
        PaymentTransaction $payment,
        string $captureId,
        int $amountMinor,
        string $currency,
        array $payload,
    ): Booking {
        if (trim($captureId) === '') {
            throw new RuntimeException('The provider payment capture reference is missing.');
        }
        if ($amountMinor !== (int) $payment->amount_minor
            || strtoupper($currency) !== strtoupper($payment->currency)) {
            throw new RuntimeException('The provider payment amount or currency does not match the immutable payment request.');
        }

        $booking = DB::transaction(function () use ($payment, $captureId, $payload): Booking {
            $booking = Booking::query()->whereKey($payment->booking_id)->lockForUpdate()->firstOrFail();
            $locked = PaymentTransaction::query()->whereKey($payment->getKey())->lockForUpdate()->firstOrFail();
            if ($locked->status === PaymentTransactionStatus::Succeeded) {
                return $booking;
            }
            $locked->update([
                'status' => PaymentTransactionStatus::Succeeded->value,
                'provider_capture_id' => $captureId,
                'provider_payload' => $payload,
                'failure_message' => null,
                'checkout_url' => null,
                'completed_at_utc' => now('UTC'),
            ]);
            PaymentTransaction::query()
                ->where('booking_id', $locked->booking_id)
                ->where('id', '!=', $locked->getKey())
                ->whereIn('status', [PaymentTransactionStatus::Pending->value, PaymentTransactionStatus::Processing->value])
                ->update([
                    'status' => PaymentTransactionStatus::Cancelled->value,
                    'failure_message' => 'Another checkout completed first.',
                    'checkout_url' => null,
                    'completed_at_utc' => now('UTC'),
                    'updated_at' => now(),
                ]);

            $this->refreshTotals($booking);

            return $booking->fresh();
        }, 3);

        if (! in_array($booking->status->value, ['cancelled', 'declined'], true)) {
            $this->workflow->refreshStatus($booking->load(['appointmentType', 'contractSubmissions', 'appointment.resources.person']));
        }

        return $booking->fresh(['organization', 'appointmentType', 'appointment', 'payments', 'refunds']);
    }

    public function markFailed(PaymentTransaction $payment, string $message, bool $cancelled = false): void
    {
        PaymentTransaction::query()
            ->whereKey($payment->getKey())
            ->where('status', '!=', PaymentTransactionStatus::Succeeded->value)
            ->update([
                'status' => $cancelled ? PaymentTransactionStatus::Cancelled->value : PaymentTransactionStatus::Failed->value,
                'failure_message' => $message,
                'checkout_url' => null,
                'completed_at_utc' => now('UTC'),
                'updated_at' => now(),
            ]);
        if ($payment->coupon_id !== null) {
            Coupon::query()->whereKey($payment->coupon_id)->where('status', CouponStatus::Pending->value)
                ->update(['status' => CouponStatus::Cancelled->value, 'updated_at' => now()]);
        }
    }

    public function refresh(Booking $booking): Booking
    {
        return DB::transaction(function () use ($booking): Booking {
            $locked = Booking::query()->whereKey($booking->getKey())->lockForUpdate()->firstOrFail();
            $this->refreshTotals($locked);

            return $locked->fresh();
        }, 3);
    }

    private function refreshTotals(Booking $booking): void
    {
        $paid = (int) $booking->payments()
            ->where('status', PaymentTransactionStatus::Succeeded->value)
            ->sum('amount_minor');
        $refunded = (int) $booking->refunds()
            ->where('status', PaymentRefundStatus::Succeeded->value)
            ->sum('amount_minor');
        $depositRefunded = (int) $booking->refunds()
            ->where('status', PaymentRefundStatus::Succeeded->value)
            ->where('refund_type', PaymentRefundType::Deposit->value)
            ->sum('amount_minor');

        $status = match (true) {
            $booking->payment_exempt && $paid === 0 => BookingPaymentStatus::Waived,
            (int) $booking->price_minor === 0 && $paid === 0 => BookingPaymentStatus::Paid,
            $paid === 0 => BookingPaymentStatus::Unpaid,
            $refunded >= $paid => BookingPaymentStatus::Refunded,
            $refunded > 0 => BookingPaymentStatus::PartiallyRefunded,
            $paid >= (int) $booking->price_minor => BookingPaymentStatus::Paid,
            default => BookingPaymentStatus::PartiallyPaid,
        };

        $booking->update([
            'paid_minor' => $paid,
            'refunded_minor' => min($paid, $refunded),
            'deposit_refunded_minor' => min((int) $booking->deposit_minor, $depositRefunded),
            'payment_status' => $status->value,
        ]);
    }
}
