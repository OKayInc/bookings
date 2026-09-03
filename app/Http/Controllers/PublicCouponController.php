<?php

namespace App\Http\Controllers;

use App\Domain\Coupons\CouponCheckoutService;
use App\Domain\Coupons\CouponQrCodeService;
use App\Domain\Money\MoneyService;
use App\Domain\Payments\PayPalPaymentGateway;
use App\Domain\Payments\PaymentStateService;
use App\Domain\Payments\PaymentRefundService;
use App\Domain\Payments\StripePaymentGateway;
use App\Enums\CouponDeliveryMethod;
use App\Enums\PaymentProvider;
use App\Models\Coupon;
use App\Models\CouponOffer;
use App\Models\Organization;
use App\Models\PaymentTransaction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

class PublicCouponController extends Controller
{
    public function index(string $organizationSlug): View
    {
        $organization = Organization::where('slug', $organizationSlug)->firstOrFail();
        $offers = $organization->couponOffers()->with('appointmentTypes')
            ->where('is_public', true)->where('is_active', true)
            ->where(fn ($query) => $query->whereNull('expires_on')->orWhereDate('expires_on', '>=', now($organization->timezone)->toDateString()))
            ->orderBy('name')->get();

        return view('public.coupons.index', compact('organization', 'offers'));
    }

    public function show(string $organizationSlug, CouponOffer $couponOffer, CouponCheckoutService $checkouts): View
    {
        $organization = Organization::where('slug', $organizationSlug)->firstOrFail();
        $this->assertPublicOffer($organization, $couponOffer);

        return view('public.coupons.show', [
            'organization' => $organization,
            'offer' => $couponOffer->load('appointmentTypes'),
            'providers' => $checkouts->availableProviders($couponOffer),
            'deliveryMethods' => CouponDeliveryMethod::cases(),
        ]);
    }

    public function purchase(Request $request, string $organizationSlug, CouponOffer $couponOffer, CouponCheckoutService $checkouts): RedirectResponse
    {
        $organization = Organization::where('slug', $organizationSlug)->firstOrFail();
        $this->assertPublicOffer($organization, $couponOffer);
        $data = $request->validate([
            'purchaser_name' => ['required', 'string', 'max:240'],
            'purchaser_email' => ['required', 'email:rfc', 'max:254'],
            'recipient_name' => ['nullable', 'string', 'max:240'],
            'recipient_email' => ['nullable', 'email:rfc', 'max:254'],
            'message' => ['nullable', 'string', 'max:5000'],
            'delivery_method' => ['required', Rule::enum(CouponDeliveryMethod::class)],
            'password' => ['required', 'string', 'min:8', 'max:200', 'confirmed'],
            'provider' => ['required', Rule::enum(PaymentProvider::class)],
        ]);
        $delivery = CouponDeliveryMethod::from($data['delivery_method']);
        if ($delivery !== CouponDeliveryMethod::Print && blank($data['recipient_email'] ?? null)) {
            return back()->withInput()->withErrors(['recipient_email' => 'Enter a recipient email for email delivery.']);
        }
        try {
            $payment = $checkouts->start($couponOffer, [
                'purchaser_name' => $data['purchaser_name'],
                'purchaser_email' => $data['purchaser_email'],
                'recipient_name' => $data['recipient_name'] ?? null,
                'recipient_email' => $data['recipient_email'] ?? null,
                'message' => $data['message'] ?? null,
                'delivery_method' => $delivery,
            ], $data['password'], PaymentProvider::from($data['provider']));
        } catch (RuntimeException $exception) {
            return back()->withInput()->withErrors(['payment' => $exception->getMessage()]);
        }

        return redirect()->away((string) $payment->checkout_url);
    }

    public function stripeReturn(Request $request, PaymentTransaction $payment, string $token, CouponCheckoutService $checkouts, StripePaymentGateway $stripe, PaymentStateService $state, PaymentRefundService $refunds): RedirectResponse|View
    {
        $checkouts->authorizeReturnToken($payment, $token);
        abort_unless($payment->provider === PaymentProvider::Stripe, 404);
        $sessionId = (string) $request->query('session_id', '');
        abort_unless($sessionId !== '' && hash_equals((string) $payment->provider_external_id, $sessionId), 404);
        try {
            if ($payment->status->value !== 'succeeded') {
                $payload = $stripe->retrieveCheckout($payment->organization->paymentSettings, $sessionId);
                if (($payload['payment_status'] ?? null) !== 'paid') {
                    throw new RuntimeException('Stripe has not confirmed this payment yet.');
                }
                $coupon = $state->completeCoupon($payment, (string) ($payload['payment_intent'] ?? ''), (int) ($payload['amount_total'] ?? 0), (string) ($payload['currency'] ?? ''), $payload);
                if ($coupon->status !== \App\Enums\CouponStatus::Active) {
                    $refunds->refundCouponPurchase($coupon, 'Automatic refund: coupon payment completed after checkout cancellation or administrative destruction.');
                    throw new RuntimeException('The purchase was no longer active, so the captured payment was sent for refund.');
                }
            } else {
                $coupon = $payment->coupon;
            }
        } catch (RuntimeException $exception) {
            return view('public.coupons.return', ['organization' => $payment->organization, 'successful' => false, 'message' => $exception->getMessage()]);
        }

        if ($coupon->status !== \App\Enums\CouponStatus::Active) {
            return view('public.coupons.return', ['organization' => $payment->organization, 'successful' => false, 'message' => 'This gift-card purchase is no longer active. Any captured payment is being refunded.']);
        }
        return $this->purchaseComplete($request, $coupon);
    }

    public function paypalReturn(Request $request, PaymentTransaction $payment, string $token, CouponCheckoutService $checkouts, PayPalPaymentGateway $paypal, PaymentStateService $state, MoneyService $money, PaymentRefundService $refunds): RedirectResponse|View
    {
        $checkouts->authorizeReturnToken($payment, $token);
        abort_unless($payment->provider === PaymentProvider::PayPal, 404);
        $orderId = (string) $request->query('token', '');
        abort_unless($orderId !== '' && hash_equals((string) $payment->provider_external_id, $orderId), 404);
        try {
            if ($payment->status->value !== 'succeeded') {
                $payload = $paypal->capture($payment->organization->paymentSettings, $orderId, $payment->idempotency_key);
                $capture = data_get($payload, 'purchase_units.0.payments.captures.0');
                if (! is_array($capture) || strtoupper((string) ($capture['status'] ?? '')) !== 'COMPLETED') {
                    throw new RuntimeException('PayPal has not confirmed this payment yet.');
                }
                $currency = (string) data_get($capture, 'amount.currency_code', '');
                $coupon = $state->completeCoupon($payment, (string) ($capture['id'] ?? ''), $money->parse((string) data_get($capture, 'amount.value', ''), $currency), $currency, $payload);
                if ($coupon->status !== \App\Enums\CouponStatus::Active) {
                    $refunds->refundCouponPurchase($coupon, 'Automatic refund: coupon payment completed after checkout cancellation or administrative destruction.');
                    throw new RuntimeException('The purchase was no longer active, so the captured payment was sent for refund.');
                }
            } else {
                $coupon = $payment->coupon;
            }
        } catch (RuntimeException $exception) {
            return view('public.coupons.return', ['organization' => $payment->organization, 'successful' => false, 'message' => $exception->getMessage()]);
        }

        if ($coupon->status !== \App\Enums\CouponStatus::Active) {
            return view('public.coupons.return', ['organization' => $payment->organization, 'successful' => false, 'message' => 'This gift-card purchase is no longer active. Any captured payment is being refunded.']);
        }
        return $this->purchaseComplete($request, $coupon);
    }

    public function cancelPayment(PaymentTransaction $payment, string $token, CouponCheckoutService $checkouts): View
    {
        $checkouts->authorizeReturnToken($payment, $token);
        $checkouts->cancel($payment);
        return view('public.coupons.return', ['organization' => $payment->organization, 'successful' => false, 'message' => 'Checkout was cancelled. You have not been charged.']);
    }

    public function view(Request $request, string $token): View
    {
        $coupon = $this->byToken($token);
        $unlocked = $request->session()->get('coupon_access.'.$coupon->uuid) === true;
        return view($unlocked ? 'public.coupons.view' : 'public.coupons.password', ['organization' => $coupon->organization, 'coupon' => $coupon, 'token' => $token]);
    }

    public function unlock(Request $request, string $token): RedirectResponse
    {
        $coupon = $this->byToken($token);
        $data = $request->validate(['password' => ['required', 'string', 'max:200']]);
        if (! Hash::check($data['password'], $coupon->password_hash)) {
            return back()->withErrors(['password' => 'The gift-card password is incorrect.']);
        }
        $request->session()->put('coupon_access.'.$coupon->uuid, true);
        return redirect()->route('public.coupons.view', $token);
    }

    public function qr(string $token, CouponQrCodeService $qr): Response
    {
        $coupon = $this->byToken($token);
        return response($qr->svg(route('public.coupons.view', $coupon->view_token)), 200, ['Content-Type' => 'image/svg+xml', 'Cache-Control' => 'private, max-age=3600']);
    }

    private function byToken(string $token): Coupon
    {
        return Coupon::query()->with(['organization', 'appointmentTypes'])->where('view_token_hash', hash('sha256', $token, true))->firstOrFail();
    }

    private function assertPublicOffer(Organization $organization, CouponOffer $offer): void
    {
        abort_unless(hash_equals($offer->organization_id, $organization->getKey()) && $offer->is_public && $offer->is_active && ! ($offer->expires_on && $offer->expires_on->lt(now($organization->timezone)->startOfDay())), 404);
    }

    private function purchaseComplete(Request $request, Coupon $coupon): RedirectResponse|View
    {
        if ($coupon->delivery_method === CouponDeliveryMethod::Print) {
            $request->session()->put('coupon_access.'.$coupon->uuid, true);
            return redirect()->route('public.coupons.view', $coupon->view_token)->with('message', 'Purchase complete. Print this page and share the password separately.');
        }
        return view('public.coupons.return', ['organization' => $coupon->organization, 'successful' => true, 'message' => 'Purchase complete. The protected gift-card delivery email has been sent.']);
    }
}
