<?php

namespace App\Domain\Payments;

use App\Enums\BookingStatus;
use App\Enums\PaymentProvider;
use App\Enums\PaymentPurpose;
use App\Enums\PaymentTransactionStatus;
use App\Models\Booking;
use App\Models\PaymentTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class PaymentCheckoutService
{
    public function __construct(
        private readonly PaymentProviderCatalog $providers,
        private readonly StripePaymentGateway $stripe,
        private readonly PayPalPaymentGateway $paypal,
        private readonly PaymentStateService $state,
    ) {
    }

    /** @return list<PaymentProvider> */
    public function availableProviders(Booking $booking): array
    {
        $booking->loadMissing('organization.paymentSettings');

        return $this->providers->available($booking->organization);
    }

    public function start(Booking $booking, PaymentProvider $provider, PaymentPurpose $purpose): PaymentTransaction
    {
        if ($purpose === PaymentPurpose::CouponPurchase) {
            throw new RuntimeException('Coupon purchases must start from the public gift-card page.');
        }
        $booking->loadMissing('organization.paymentSettings');
        if (! $this->providers->isAvailable($booking->organization, $provider)) {
            throw new RuntimeException($provider->label().' is not configured for this organization.');
        }

        $returnToken = Str::random(64);
        [$payment, $isNew] = DB::transaction(function () use ($booking, $provider, $purpose, $returnToken): array {
            $locked = Booking::query()->whereKey($booking->getKey())->lockForUpdate()->firstOrFail();
            if (in_array($locked->status, [BookingStatus::Cancelled, BookingStatus::Declined], true)) {
                throw new RuntimeException('A cancelled or declined booking cannot accept payment.');
            }
            if ($locked->status === BookingStatus::PendingPayment && $locked->expires_at_utc?->isPast()) {
                throw new RuntimeException('The payment window has expired. Please contact the organization.');
            }
            $netPaid = $locked->netPaidMinor();
            $initialOutstanding = max(0, (int) $locked->initial_payment_due_minor - $netPaid);
            if ($purpose === PaymentPurpose::Initial) {
                if ($locked->status !== BookingStatus::PendingPayment || $initialOutstanding <= 0) {
                    throw new RuntimeException('This booking does not have an initial payment due.');
                }
                $amount = $initialOutstanding;
            } else {
                if ($initialOutstanding > 0) {
                    throw new RuntimeException('The initial payment must be completed before paying the remaining balance.');
                }
                if ($locked->status !== BookingStatus::Confirmed) {
                    throw new RuntimeException('The balance can be paid after the booking prerequisites are complete.');
                }
                $amount = $locked->outstandingMinor();
                if ($amount <= 0) {
                    throw new RuntimeException('This booking has no outstanding balance.');
                }
            }

            PaymentTransaction::query()
                ->where('booking_id', $locked->getKey())
                ->whereIn('status', [PaymentTransactionStatus::Pending->value, PaymentTransactionStatus::Processing->value])
                ->where('expires_at_utc', '<=', now('UTC'))
                ->update([
                    'status' => PaymentTransactionStatus::Cancelled->value,
                    'failure_message' => 'Checkout expired before completion.',
                    'checkout_url' => null,
                    'completed_at_utc' => now('UTC'),
                    'updated_at' => now(),
                ]);

            $existing = PaymentTransaction::query()
                ->where('booking_id', $locked->getKey())
                ->where('provider', $provider->value)
                ->where('purpose', $purpose->value)
                ->whereIn('status', [PaymentTransactionStatus::Pending->value, PaymentTransactionStatus::Processing->value])
                ->where('amount_minor', $amount)
                ->where('expires_at_utc', '>', now('UTC'))
                ->latest()
                ->first();
            if ($existing !== null) {
                if (filled($existing->checkout_url)) {
                    return [$existing, false];
                }

                throw new RuntimeException('A checkout is already being prepared. Please try again in a moment.');
            }

            PaymentTransaction::query()
                ->where('booking_id', $locked->getKey())
                ->whereIn('status', [PaymentTransactionStatus::Pending->value, PaymentTransactionStatus::Processing->value])
                ->update([
                    'status' => PaymentTransactionStatus::Cancelled->value,
                    'failure_message' => 'Replaced by a newer checkout request.',
                    'checkout_url' => null,
                    'completed_at_utc' => now('UTC'),
                    'updated_at' => now(),
                ]);

            $payment = PaymentTransaction::create([
                'organization_id' => $locked->organization_id,
                'booking_id' => $locked->getKey(),
                'provider' => $provider->value,
                'purpose' => $purpose->value,
                'status' => PaymentTransactionStatus::Pending->value,
                'amount_minor' => $amount,
                'currency' => $locked->currency,
                'idempotency_key' => (string) Str::uuid(),
                'return_token_hash' => hash('sha256', $returnToken, true),
                'expires_at_utc' => now('UTC')->addMinutes(max(15, (int) config('payments.checkout_ttl_minutes', 60))),
            ]);

            return [$payment, true];
        }, 3);

        if (! $isNew) {
            return $payment;
        }

        $settings = $booking->organization->paymentSettings;
        $returnRoute = $provider === PaymentProvider::Stripe
            ? 'public.payments.stripe.return'
            : 'public.payments.paypal.return';
        $successUrl = route($returnRoute, [$payment, $returnToken]);
        if ($provider === PaymentProvider::Stripe) {
            $successUrl .= (str_contains($successUrl, '?') ? '&' : '?').'session_id={CHECKOUT_SESSION_ID}';
        }
        $cancelUrl = route('public.payments.cancel', [$payment, $returnToken]);

        try {
            $checkout = match ($provider) {
                PaymentProvider::Stripe => $this->stripe->createCheckout($settings, $payment, $successUrl, $cancelUrl),
                PaymentProvider::PayPal => $this->paypal->createCheckout($settings, $payment, $successUrl, $cancelUrl),
            };
            $payment->update([
                'provider_external_id' => $checkout['id'],
                'provider_capture_id' => $checkout['payment_intent'] ?? null,
                'checkout_url' => $checkout['url'],
                'provider_payload' => $checkout['payload'],
            ]);
        } catch (\Throwable $exception) {
            $this->state->markFailed($payment, $exception->getMessage());
            throw $exception instanceof RuntimeException
                ? $exception
                : new PaymentGatewayException('The payment provider could not start checkout.', previous: $exception);
        }

        return $payment->fresh();
    }

    public function cancel(PaymentTransaction $payment): void
    {
        if ($payment->status !== PaymentTransactionStatus::Succeeded) {
            $this->state->markFailed($payment, 'The client cancelled checkout.', true);
        }
    }

    public function authorizeReturnToken(PaymentTransaction $payment, string $token): void
    {
        abort_unless(hash_equals($payment->return_token_hash, hash('sha256', $token, true)), 404);
    }
}
