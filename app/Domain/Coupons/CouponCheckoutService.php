<?php

namespace App\Domain\Coupons;

use App\Domain\Payments\PayPalPaymentGateway;
use App\Domain\Payments\PaymentGatewayException;
use App\Domain\Payments\PaymentProviderCatalog;
use App\Domain\Payments\PaymentStateService;
use App\Domain\Payments\StripePaymentGateway;
use App\Enums\CouponStatus;
use App\Enums\PaymentProvider;
use App\Enums\PaymentPurpose;
use App\Enums\PaymentTransactionStatus;
use App\Models\Coupon;
use App\Models\CouponOffer;
use App\Models\PaymentTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class CouponCheckoutService
{
    public function __construct(
        private readonly CouponIssuanceService $issuance,
        private readonly PaymentProviderCatalog $providers,
        private readonly StripePaymentGateway $stripe,
        private readonly PayPalPaymentGateway $paypal,
        private readonly PaymentStateService $state,
    ) {
    }

    /** @return list<PaymentProvider> */
    public function availableProviders(CouponOffer $offer): array
    {
        return $this->providers->available($offer->organization);
    }

    /** @param array<string,mixed> $recipient */
    public function start(CouponOffer $offer, array $recipient, string $password, PaymentProvider $provider): PaymentTransaction
    {
        $offer->loadMissing('organization.paymentSettings');
        if (! $this->providers->isAvailable($offer->organization, $provider)) {
            throw new RuntimeException($provider->label().' is not configured for this organization.');
        }
        $coupon = $this->issuance->fromOffer($offer, $recipient, $password);
        $returnToken = Str::random(64);
        $payment = PaymentTransaction::create([
            'organization_id' => $offer->organization_id,
            'coupon_id' => $coupon->getKey(),
            'provider' => $provider->value,
            'purpose' => PaymentPurpose::CouponPurchase->value,
            'status' => PaymentTransactionStatus::Pending->value,
            'amount_minor' => $offer->purchase_price_minor,
            'currency' => $offer->organization->currency,
            'idempotency_key' => (string) Str::uuid(),
            'return_token_hash' => hash('sha256', $returnToken, true),
            'expires_at_utc' => now('UTC')->addMinutes(max(15, (int) config('payments.checkout_ttl_minutes', 60))),
        ]);
        $returnRoute = $provider === PaymentProvider::Stripe ? 'public.coupon-payments.stripe.return' : 'public.coupon-payments.paypal.return';
        $successUrl = route($returnRoute, [$payment, $returnToken]);
        if ($provider === PaymentProvider::Stripe) {
            $successUrl .= (str_contains($successUrl, '?') ? '&' : '?').'session_id={CHECKOUT_SESSION_ID}';
        }
        $cancelUrl = route('public.coupon-payments.cancel', [$payment, $returnToken]);
        try {
            $checkout = $provider === PaymentProvider::Stripe
                ? $this->stripe->createCheckout($offer->organization->paymentSettings, $payment, $successUrl, $cancelUrl)
                : $this->paypal->createCheckout($offer->organization->paymentSettings, $payment, $successUrl, $cancelUrl);
            $payment->update([
                'provider_external_id' => $checkout['id'],
                'provider_capture_id' => $checkout['payment_intent'] ?? null,
                'checkout_url' => $checkout['url'],
                'provider_payload' => $checkout['payload'],
            ]);
        } catch (\Throwable $exception) {
            $this->state->markFailed($payment, $exception->getMessage());
            $coupon->update(['status' => CouponStatus::Cancelled->value]);
            throw $exception instanceof RuntimeException ? $exception : new PaymentGatewayException('The payment provider could not start checkout.', previous: $exception);
        }

        $payment->setAttribute('return_token', $returnToken);
        return $payment->fresh(['coupon']);
    }

    public function authorizeReturnToken(PaymentTransaction $payment, string $token): void
    {
        abort_unless(hash_equals($payment->return_token_hash, hash('sha256', $token, true)), 404);
        abort_unless($payment->coupon_id !== null && $payment->booking_id === null, 404);
    }

    public function cancel(PaymentTransaction $payment): void
    {
        if ($payment->status !== PaymentTransactionStatus::Succeeded) {
            DB::transaction(function () use ($payment): void {
                $this->state->markFailed($payment, 'The purchaser cancelled checkout.', true);
                Coupon::query()->whereKey($payment->coupon_id)->where('status', CouponStatus::Pending->value)->update(['status' => CouponStatus::Cancelled->value]);
            });
        }
    }
}
