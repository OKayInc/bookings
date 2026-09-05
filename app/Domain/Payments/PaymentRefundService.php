<?php

namespace App\Domain\Payments;

use App\Enums\PaymentProvider;
use App\Enums\PaymentPurpose;
use App\Enums\PaymentRefundStatus;
use App\Enums\PaymentRefundType;
use App\Enums\PaymentTransactionStatus;
use App\Models\Booking;
use App\Models\PaymentRefund;
use App\Models\Person;
use App\Models\PaymentTransaction;
use App\Models\Coupon;
use App\Enums\CouponStatus;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

class PaymentRefundService
{
    public function __construct(
        private readonly StripePaymentGateway $stripe,
        private readonly PayPalPaymentGateway $paypal,
        private readonly PaymentStateService $state,
    ) {
    }

    public function safeRefundForCancellation(Booking $booking): void
    {
        try {
            $this->refundForCancellation($booking);
        } catch (\Throwable $exception) {
            Log::error('Automatic booking refund failed.', [
                'booking_uuid' => $booking->uuid,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    /** @return Collection<int,PaymentRefund> */
    public function refundForCancellation(Booking $booking): Collection
    {
        $reason = 'Automatic refund for '.str_replace('_', ' ', (string) $booking->cancellation_origin).' cancellation.';
        $prepared = DB::transaction(function () use ($booking, $reason): Collection {
            $locked = Booking::query()->whereKey($booking->getKey())->lockForUpdate()->firstOrFail();
            $percentage = $locked->cancellation_origin === 'client'
                ? (int) $locked->client_refund_percentage_bps
                : (int) $locked->staff_refund_percentage_bps;
            $percentage = min(10000, max(0, $percentage));
            $grossPaid = (int) $locked->payments()
                ->where('status', PaymentTransactionStatus::Succeeded->value)
                ->sum('amount_minor');
            $initialPaid = (int) $locked->payments()
                ->where('status', PaymentTransactionStatus::Succeeded->value)
                ->where('purpose', PaymentPurpose::Initial->value)
                ->sum('amount_minor');
            $technicalExcess = max(
                max(0, $grossPaid - (int) $locked->price_minor),
                max(0, $initialPaid - (int) $locked->initial_payment_due_minor),
            );
            $depositPaid = min(
                (int) $locked->deposit_minor,
                (int) $locked->payments()
                    ->where('status', PaymentTransactionStatus::Succeeded->value)
                    ->sum('deposit_amount_minor'),
            );
            $depositAllocated = (int) $locked->refunds()
                ->whereIn('status', [PaymentRefundStatus::Pending->value, PaymentRefundStatus::Succeeded->value])
                ->where('refund_type', PaymentRefundType::Deposit->value)
                ->sum('amount_minor');
            $prepared = $this->prepareDepositRefunds(
                $locked,
                max(0, $depositPaid - $depositAllocated),
                'Automatic refundable-deposit return after booking cancellation.',
            );

            $servicePaid = max(0, $grossPaid - $technicalExcess - $depositPaid);
            $targetGeneralRefund = $technicalExcess + $this->percentage($servicePaid, $percentage);
            $generalAllocated = (int) $locked->refunds()
                ->whereIn('status', [PaymentRefundStatus::Pending->value, PaymentRefundStatus::Succeeded->value])
                ->where('refund_type', '!=', PaymentRefundType::Deposit->value)
                ->sum('amount_minor');

            return $prepared->concat($this->prepareRefunds(
                $locked,
                max(0, $targetGeneralRefund - $generalAllocated),
                $reason,
                preserveDeposit: false,
            ));
        }, 3);

        return $this->sendPrepared($prepared);
    }

    /** @return Collection<int,PaymentRefund> */
    public function refundAmount(Booking $booking, int $amountMinor, string $reason, ?Person $requestedBy = null): Collection
    {
        if ($amountMinor <= 0) {
            return collect();
        }

        $prepared = DB::transaction(function () use ($booking, $amountMinor, $reason, $requestedBy): Collection {
            $locked = Booking::query()->whereKey($booking->getKey())->lockForUpdate()->firstOrFail();

            return $this->prepareRefunds($locked, $amountMinor, $reason, $requestedBy);
        }, 3);

        return $this->sendPrepared($prepared);
    }

    /** @return Collection<int,PaymentRefund> */
    public function refundDeposit(Booking $booking, int $amountMinor, string $reason, ?Person $requestedBy = null): Collection
    {
        if ($amountMinor <= 0) {
            throw new RuntimeException('The deposit refund amount must be greater than zero.');
        }

        $prepared = DB::transaction(function () use ($booking, $amountMinor, $reason, $requestedBy): Collection {
            $locked = Booking::query()->whereKey($booking->getKey())->lockForUpdate()->firstOrFail();
            $allocated = (int) $locked->refunds()
                ->whereIn('status', [PaymentRefundStatus::Pending->value, PaymentRefundStatus::Succeeded->value])
                ->where('refund_type', PaymentRefundType::Deposit->value)
                ->sum('amount_minor');
            $remaining = max(0, (int) $locked->deposit_minor - $allocated);
            if ($amountMinor > $remaining) {
                throw new RuntimeException('The requested refund exceeds the remaining refundable deposit.');
            }

            return $this->prepareDepositRefunds($locked, $amountMinor, $reason, $requestedBy);
        }, 3);

        return $this->sendPrepared($prepared);
    }

    public function refundableDepositMinor(Booking $booking): int
    {
        $transactions = $booking->payments()
            ->where('status', PaymentTransactionStatus::Succeeded->value)
            ->oldest('completed_at_utc')
            ->with('refunds')
            ->get();

        return min($booking->depositRemainingMinor(), $this->depositCapacity($booking, $transactions));
    }

    public function refundablePriceMinor(Booking $booking): int
    {
        $transactions = $booking->payments()
            ->where('status', PaymentTransactionStatus::Succeeded->value)
            ->oldest('completed_at_utc')
            ->with('refunds')
            ->get();
        $available = (int) $transactions->sum(function (PaymentTransaction $transaction): int {
            $allocated = (int) $transaction->refunds
                ->whereIn('status', [PaymentRefundStatus::Pending, PaymentRefundStatus::Succeeded])
                ->sum('amount_minor');

            return max(0, (int) $transaction->amount_minor - $allocated);
        });

        return max(0, $available - $this->depositCapacity($booking, $transactions));
    }

    /** @return Collection<int,PaymentRefund> */
    public function refundTransactionBalance(PaymentTransaction $transaction, string $reason): Collection
    {
        $prepared = DB::transaction(function () use ($transaction, $reason): Collection {
            $booking = Booking::query()->whereKey($transaction->booking_id)->lockForUpdate()->firstOrFail();
            $locked = PaymentTransaction::query()->whereKey($transaction->getKey())->lockForUpdate()->firstOrFail();
            if ($locked->status !== PaymentTransactionStatus::Succeeded) {
                return collect();
            }
            $allocated = (int) $locked->refunds()
                ->whereIn('status', [PaymentRefundStatus::Pending->value, PaymentRefundStatus::Succeeded->value])
                ->sum('amount_minor');
            $available = max(0, (int) $locked->amount_minor - $allocated);
            if ($available === 0) {
                return collect();
            }

            $depositAllocated = (int) $booking->refunds()
                ->whereIn('status', [PaymentRefundStatus::Pending->value, PaymentRefundStatus::Succeeded->value])
                ->where('refund_type', PaymentRefundType::Deposit->value)
                ->sum('amount_minor');
            $depositPortion = min(
                $available,
                max(0, (int) $booking->deposit_minor - $depositAllocated),
                max(0, (int) $locked->deposit_amount_minor),
            );
            $created = collect();
            if ($depositPortion > 0) {
                $created->push($this->createRefund(
                    $booking,
                    $locked,
                    $depositPortion,
                    $reason,
                    type: PaymentRefundType::Deposit,
                ));
            }
            if ($available > $depositPortion) {
                $created->push($this->createRefund(
                    $booking,
                    $locked,
                    $available - $depositPortion,
                    $reason,
                ));
            }

            return $created;
        }, 3);

        return $this->sendPrepared($prepared);
    }

    /** @return Collection<int,PaymentRefund> */
    public function refundOverpayment(PaymentTransaction $transaction): Collection
    {
        $prepared = DB::transaction(function () use ($transaction): Collection {
            $booking = Booking::query()
                ->whereKey($transaction->booking_id)
                ->lockForUpdate()
                ->firstOrFail();
            $lockedTransaction = PaymentTransaction::query()
                ->whereKey($transaction->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            if ($lockedTransaction->status !== PaymentTransactionStatus::Succeeded) {
                return collect();
            }

            $grossPaid = (int) $booking->payments()
                ->where('status', PaymentTransactionStatus::Succeeded->value)
                ->sum('amount_minor');
            $allocated = (int) $booking->refunds()
                ->whereIn('status', [PaymentRefundStatus::Pending->value, PaymentRefundStatus::Succeeded->value])
                ->sum('amount_minor');
            $totalExcess = max(0, $grossPaid - $allocated - (int) $booking->price_minor);
            $initialPaid = (int) $booking->payments()
                ->where('status', PaymentTransactionStatus::Succeeded->value)
                ->where('purpose', PaymentPurpose::Initial->value)
                ->sum('amount_minor');
            $initialAllocated = (int) $booking->refunds()
                ->whereIn('status', [PaymentRefundStatus::Pending->value, PaymentRefundStatus::Succeeded->value])
                ->whereHas('transaction', fn ($query) => $query->where('purpose', PaymentPurpose::Initial->value))
                ->sum('amount_minor');
            $duplicateInitial = $lockedTransaction->purpose === PaymentPurpose::Initial
                ? max(0, $initialPaid - $initialAllocated - (int) $booking->initial_payment_due_minor)
                : 0;
            $excess = max($totalExcess, $duplicateInitial);
            if ($excess === 0) {
                return collect();
            }

            $transactionAllocated = (int) $lockedTransaction->refunds()
                ->whereIn('status', [PaymentRefundStatus::Pending->value, PaymentRefundStatus::Succeeded->value])
                ->sum('amount_minor');
            $amount = min($excess, max(0, (int) $lockedTransaction->amount_minor - $transactionAllocated));
            if ($amount === 0) {
                return collect();
            }

            return collect([$this->createRefund(
                $booking,
                $lockedTransaction,
                $amount,
                'Automatic refund: duplicate or excess payment capture.',
            )]);
        }, 3);

        return $this->sendPrepared($prepared);
    }

    /** @return Collection<int,PaymentRefund> */
    public function refundCouponPurchase(Coupon $coupon, string $reason, ?Person $requestedBy = null): Collection
    {
        $prepared = DB::transaction(function () use ($coupon, $reason, $requestedBy): Collection {
            $locked = Coupon::query()->whereKey($coupon->getKey())->lockForUpdate()->firstOrFail();
            return $this->prepareCouponRefund($locked, $reason, $requestedBy);
        }, 3);

        return $this->sendPrepared($prepared);
    }

    /** @return Collection<int,PaymentRefund> */
    public function destroyCoupon(Coupon $coupon, string $reason, Person $destroyedBy): Collection
    {
        $prepared = DB::transaction(function () use ($coupon, $reason, $destroyedBy): Collection {
            $locked = Coupon::query()->whereKey($coupon->getKey())->lockForUpdate()->firstOrFail();
            if ($locked->redemptions()->exists() || $locked->status === CouponStatus::Used) {
                throw new RuntimeException('A gift card or coupon that has been used cannot be destroyed.');
            }
            if ($locked->status === CouponStatus::Destroyed) {
                throw new RuntimeException('This gift card or coupon is already destroyed.');
            }
            $locked->update([
                'status' => CouponStatus::Destroyed->value,
                'destroyed_at_utc' => now('UTC'),
                'destroyed_by_person_id' => $destroyedBy->getKey(),
                'destruction_reason' => $reason,
            ]);

            return $this->prepareCouponRefund($locked, 'Administrative coupon destruction: '.$reason, $destroyedBy);
        }, 3);

        return $this->sendPrepared($prepared);
    }

    /** @return Collection<int,PaymentRefund> */
    private function prepareCouponRefund(Coupon $coupon, string $reason, ?Person $requestedBy): Collection
    {
        if ($coupon->redemptions()->exists()) {
            throw new RuntimeException('A gift card or coupon that has been used cannot be destroyed or refunded.');
        }
        $transaction = $coupon->payments()
            ->where('status', PaymentTransactionStatus::Succeeded->value)
            ->lockForUpdate()
            ->first();
        if ($transaction === null) {
            return collect();
        }
        $allocated = (int) $transaction->refunds()
            ->whereIn('status', [PaymentRefundStatus::Pending->value, PaymentRefundStatus::Succeeded->value])
            ->sum('amount_minor');
        $amount = max(0, (int) $transaction->amount_minor - $allocated);
        if ($amount === 0) {
            return collect();
        }

        return collect([PaymentRefund::create([
            'organization_id' => $coupon->organization_id,
            'coupon_id' => $coupon->getKey(),
            'payment_transaction_id' => $transaction->getKey(),
            'requested_by_person_id' => $requestedBy?->getKey(),
            'provider' => $transaction->provider->value,
            'status' => PaymentRefundStatus::Pending->value,
            'refund_type' => PaymentRefundType::General->value,
            'amount_minor' => $amount,
            'currency' => $transaction->currency,
            'idempotency_key' => (string) Str::uuid(),
            'reason' => $reason,
        ])]);
    }

    /** @return Collection<int,PaymentRefund> */
    private function prepareRefunds(
        Booking $booking,
        int $amountMinor,
        string $reason,
        ?Person $requestedBy = null,
        bool $preserveDeposit = true,
    ): Collection {
        if ($amountMinor <= 0) {
            return collect();
        }

        $transactions = $booking->payments()
            ->where('status', PaymentTransactionStatus::Succeeded->value)
            ->oldest('completed_at_utc')
            ->lockForUpdate()
            ->with('refunds')
            ->get();

        $depositComponents = $preserveDeposit ? $this->depositComponents($booking, $transactions) : [];
        $availableTotal = $transactions->sum(function (PaymentTransaction $transaction) use ($depositComponents): int {
            $allocated = (int) $transaction->refunds
                ->whereIn('status', [PaymentRefundStatus::Pending, PaymentRefundStatus::Succeeded])
                ->sum('amount_minor');
            $depositRefunds = (int) $transaction->refunds
                ->where('refund_type', PaymentRefundType::Deposit)
                ->whereIn('status', [PaymentRefundStatus::Pending, PaymentRefundStatus::Succeeded])
                ->sum('amount_minor');
            $depositReserve = max(0, ($depositComponents[$transaction->getKey()] ?? 0) - $depositRefunds);

            return max(0, (int) $transaction->amount_minor - $allocated - $depositReserve);
        });
        if ($availableTotal < $amountMinor) {
            throw new RuntimeException('The requested price refund exceeds the captured amount available after reserving the refundable deposit. Use the deposit-refund control to return deposit funds.');
        }

        $created = collect();
        $remaining = $amountMinor;
        foreach ($transactions as $transaction) {
            if ($remaining <= 0) {
                break;
            }
            $alreadyAllocated = (int) $transaction->refunds
                ->whereIn('status', [PaymentRefundStatus::Pending, PaymentRefundStatus::Succeeded])
                ->sum('amount_minor');
            $depositRefunds = (int) $transaction->refunds
                ->where('refund_type', PaymentRefundType::Deposit)
                ->whereIn('status', [PaymentRefundStatus::Pending, PaymentRefundStatus::Succeeded])
                ->sum('amount_minor');
            $depositReserve = max(0, ($depositComponents[$transaction->getKey()] ?? 0) - $depositRefunds);
            $available = max(0, (int) $transaction->amount_minor - $alreadyAllocated - $depositReserve);
            if ($available === 0) {
                continue;
            }

            $portion = min($remaining, $available);
            $created->push($this->createRefund($booking, $transaction, $portion, $reason, $requestedBy));
            $remaining -= $portion;
        }

        return $created;
    }

    /** @return Collection<int,PaymentRefund> */
    private function prepareDepositRefunds(
        Booking $booking,
        int $amountMinor,
        string $reason,
        ?Person $requestedBy = null,
    ): Collection {
        if ($amountMinor <= 0) {
            return collect();
        }

        $transactions = $booking->payments()
            ->where('status', PaymentTransactionStatus::Succeeded->value)
            ->oldest('completed_at_utc')
            ->lockForUpdate()
            ->with('refunds')
            ->get();
        $components = $this->depositComponents($booking, $transactions);
        $created = collect();
        $remaining = $amountMinor;

        foreach ($transactions as $transaction) {
            if ($remaining <= 0) {
                break;
            }
            $component = $components[$transaction->getKey()] ?? 0;
            $depositAllocated = (int) $transaction->refunds
                ->where('refund_type', PaymentRefundType::Deposit)
                ->whereIn('status', [PaymentRefundStatus::Pending, PaymentRefundStatus::Succeeded])
                ->sum('amount_minor');
            $allAllocated = (int) $transaction->refunds
                ->whereIn('status', [PaymentRefundStatus::Pending, PaymentRefundStatus::Succeeded])
                ->sum('amount_minor');
            $available = min(
                max(0, $component - $depositAllocated),
                max(0, (int) $transaction->amount_minor - $allAllocated),
            );
            if ($available === 0) {
                continue;
            }

            $portion = min($remaining, $available);
            $created->push($this->createRefund(
                $booking,
                $transaction,
                $portion,
                $reason,
                $requestedBy,
                PaymentRefundType::Deposit,
            ));
            $remaining -= $portion;
        }

        if ($remaining > 0) {
            throw new RuntimeException('The requested deposit refund exceeds the deposit collected through completed online payments.');
        }

        return $created;
    }

    /** @param Collection<int,PaymentTransaction> $transactions
     *  @return array<string,int>
     */
    private function depositComponents(Booking $booking, Collection $transactions): array
    {
        $remaining = max(0, (int) $booking->deposit_minor);
        $components = [];
        foreach ($transactions as $transaction) {
            $component = min($remaining, max(0, (int) $transaction->deposit_amount_minor));
            $components[$transaction->getKey()] = $component;
            $remaining -= $component;
        }

        return $components;
    }

    /** @param Collection<int,PaymentTransaction> $transactions */
    private function depositCapacity(Booking $booking, Collection $transactions): int
    {
        $components = $this->depositComponents($booking, $transactions);

        return (int) $transactions->sum(function (PaymentTransaction $transaction) use ($components): int {
            $depositAllocated = (int) $transaction->refunds
                ->where('refund_type', PaymentRefundType::Deposit)
                ->whereIn('status', [PaymentRefundStatus::Pending, PaymentRefundStatus::Succeeded])
                ->sum('amount_minor');
            $allAllocated = (int) $transaction->refunds
                ->whereIn('status', [PaymentRefundStatus::Pending, PaymentRefundStatus::Succeeded])
                ->sum('amount_minor');

            return min(
                max(0, ($components[$transaction->getKey()] ?? 0) - $depositAllocated),
                max(0, (int) $transaction->amount_minor - $allAllocated),
            );
        });
    }

    private function createRefund(
        Booking $booking,
        PaymentTransaction $transaction,
        int $amountMinor,
        string $reason,
        ?Person $requestedBy = null,
        PaymentRefundType $type = PaymentRefundType::General,
    ): PaymentRefund {
        return PaymentRefund::create([
            'organization_id' => $booking->organization_id,
            'booking_id' => $booking->getKey(),
            'payment_transaction_id' => $transaction->getKey(),
            'requested_by_person_id' => $requestedBy?->getKey(),
            'provider' => $transaction->provider->value,
            'status' => PaymentRefundStatus::Pending->value,
            'refund_type' => $type->value,
            'amount_minor' => $amountMinor,
            'currency' => $booking->currency,
            'idempotency_key' => (string) Str::uuid(),
            'reason' => $reason,
        ]);
    }

    /** @param Collection<int,PaymentRefund> $refunds
     *  @return Collection<int,PaymentRefund>
     */
    private function sendPrepared(Collection $refunds): Collection
    {
        return $refunds->map(fn (PaymentRefund $refund): PaymentRefund => $this->send(
            $refund->load(['transaction', 'booking', 'coupon', 'organization.paymentSettings']),
        ));
    }

    public function send(PaymentRefund $refund): PaymentRefund
    {
        if ($refund->status === PaymentRefundStatus::Succeeded) {
            return $refund;
        }

        $settings = $refund->organization->paymentSettings;
        if ($settings === null) {
            throw new RuntimeException('Payment credentials are not configured.');
        }

        try {
            $payload = match ($refund->provider) {
                PaymentProvider::Stripe => $this->stripe->refund($settings, $refund),
                PaymentProvider::PayPal => $this->paypal->refund($settings, $refund),
            };
            $externalId = (string) ($payload['id'] ?? '');
            if ($externalId === '') {
                throw new PaymentGatewayException('The payment provider returned a refund without a reference.');
            }
            $rawStatus = strtoupper((string) ($payload['status'] ?? ''));
            $succeeded = in_array($rawStatus, ['SUCCEEDED', 'COMPLETED'], true);
            $failed = in_array($rawStatus, ['FAILED', 'CANCELED', 'CANCELLED'], true);
            $refund->update([
                'provider_refund_id' => $externalId,
                'status' => $succeeded
                    ? PaymentRefundStatus::Succeeded->value
                    : ($failed ? PaymentRefundStatus::Failed->value : PaymentRefundStatus::Pending->value),
                'provider_payload' => $payload,
                'failure_message' => $failed ? 'The provider declined the refund.' : null,
                'completed_at_utc' => $succeeded || $failed ? now('UTC') : null,
            ]);
        } catch (\Throwable $exception) {
            $refund->update([
                // A timeout can happen after a provider accepted the request. Keep the
                // allocation pending so a retry reuses the same provider idempotency key.
                'status' => PaymentRefundStatus::Pending->value,
                'failure_message' => $exception->getMessage(),
                'completed_at_utc' => null,
            ]);
        }

        if ($refund->booking !== null) {
            $this->state->refresh($refund->booking);
        } elseif ($refund->coupon !== null && $refund->status === PaymentRefundStatus::Succeeded) {
            $refund->coupon->update(['refunded_at_utc' => now('UTC')]);
        }

        return $refund->fresh();
    }

    private function percentage(int $amountMinor, int $basisPoints): int
    {
        if ($amountMinor === 0 || $basisPoints === 0) {
            return 0;
        }
        if ($amountMinor > intdiv(PHP_INT_MAX - 5000, $basisPoints)) {
            throw new RuntimeException('The refund calculation is too large.');
        }

        return intdiv(($amountMinor * $basisPoints) + 5000, 10000);
    }
}
