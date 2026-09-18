<?php

namespace App\Http\Controllers;

use App\Domain\Money\MoneyService;
use App\Domain\Payments\PayPalPaymentGateway;
use App\Domain\Payments\PaymentCheckoutService;
use App\Domain\Payments\PaymentRefundService;
use App\Domain\Payments\PaymentStateService;
use App\Domain\Payments\StripePaymentGateway;
use App\Enums\PaymentProvider;
use App\Enums\PaymentPurpose;
use App\Models\Booking;
use App\Models\PaymentTransaction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;

class PublicPaymentController extends Controller
{
    public function start(
        Request $request,
        Booking $booking,
        string $token,
        PaymentCheckoutService $checkouts,
    ): RedirectResponse {
        $this->authorizeManageToken($booking, $token);
        $data = $request->validate([
            'provider' => ['required', Rule::enum(PaymentProvider::class)],
            'purpose' => ['required', Rule::in([PaymentPurpose::Initial->value, PaymentPurpose::Balance->value])],
        ]);

        try {
            $payment = $checkouts->start(
                $booking,
                PaymentProvider::from($data['provider']),
                PaymentPurpose::from($data['purpose']),
            );
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        $request->session()->put('payment_manage_tokens.'.$payment->uuid, $token);

        return redirect()->away((string) $payment->checkout_url);
    }

    public function stripeReturn(
        Request $request,
        PaymentTransaction $payment,
        string $token,
        PaymentCheckoutService $checkouts,
        StripePaymentGateway $stripe,
        PaymentStateService $state,
        PaymentRefundService $refunds,
    ): RedirectResponse|View {
        $checkouts->authorizeReturnToken($payment, $token);
        abort_unless($payment->provider === PaymentProvider::Stripe, 404);
        if ($payment->status->value === 'succeeded') {
            return $this->finishSuccess($request, $payment, $payment->booking);
        }
        $sessionId = (string) $request->query('session_id', '');
        abort_unless($sessionId !== '' && hash_equals((string) $payment->provider_external_id, $sessionId), 404);

        try {
            $payload = $stripe->retrieveCheckout($payment->organization->paymentSettings, $sessionId);
            if (($payload['payment_status'] ?? null) !== 'paid') {
                throw new RuntimeException('Stripe has not confirmed this payment yet.');
            }
            $booking = $state->complete(
                $payment,
                (string) ($payload['payment_intent'] ?? ''),
                (int) ($payload['amount_total'] ?? 0),
                (string) ($payload['currency'] ?? ''),
                $payload,
            );
            $this->refundIfCancelled($booking, $payment, $refunds);
        } catch (RuntimeException $exception) {
            return $this->finish($request, $payment, 'error', $exception->getMessage());
        }

        return $this->finishSuccess($request, $payment, $booking);
    }

    public function paypalReturn(
        Request $request,
        PaymentTransaction $payment,
        string $token,
        PaymentCheckoutService $checkouts,
        PayPalPaymentGateway $paypal,
        PaymentStateService $state,
        PaymentRefundService $refunds,
        MoneyService $money,
    ): RedirectResponse|View {
        $checkouts->authorizeReturnToken($payment, $token);
        abort_unless($payment->provider === PaymentProvider::PayPal, 404);
        if ($payment->status->value === 'succeeded') {
            return $this->finishSuccess($request, $payment, $payment->booking);
        }
        $orderId = (string) $request->query('token', '');
        abort_unless($orderId !== '' && hash_equals((string) $payment->provider_external_id, $orderId), 404);

        try {
            $payload = $paypal->capture($payment->organization->paymentSettings, $orderId, $payment->idempotency_key);
            $capture = data_get($payload, 'purchase_units.0.payments.captures.0');
            if (! is_array($capture) || strtoupper((string) ($capture['status'] ?? '')) !== 'COMPLETED') {
                throw new RuntimeException('PayPal has not confirmed this payment yet.');
            }
            $currency = (string) data_get($capture, 'amount.currency_code', '');
            $booking = $state->complete(
                $payment,
                (string) ($capture['id'] ?? ''),
                $money->parse((string) data_get($capture, 'amount.value', ''), $currency),
                $currency,
                $payload,
            );
            $this->refundIfCancelled($booking, $payment, $refunds);
        } catch (RuntimeException $exception) {
            return $this->finish($request, $payment, 'error', $exception->getMessage());
        }

        return $this->finishSuccess($request, $payment, $booking);
    }

    public function cancel(
        Request $request,
        PaymentTransaction $payment,
        string $token,
        PaymentCheckoutService $checkouts,
    ): RedirectResponse|View {
        $checkouts->authorizeReturnToken($payment, $token);
        $checkouts->cancel($payment);

        return $this->finish($request, $payment, 'message', 'Checkout was cancelled. Your booking has not been charged.');
    }

    private function finish(Request $request, PaymentTransaction $payment, string $flash, string $message): RedirectResponse|View
    {
        $manageToken = $request->session()->pull('payment_manage_tokens.'.$payment->uuid);
        if (is_string($manageToken) && $manageToken !== '') {
            return redirect()->route('public.bookings.manage', [$payment->booking, $manageToken])->with($flash, $message);
        }

        return view('public.payments.return', [
            'organization' => $payment->organization,
            'payment' => $payment->fresh(),
            'message' => $message,
            'successful' => $flash === 'success',
        ]);
    }

    private function finishSuccess(
        Request $request,
        PaymentTransaction $payment,
        Booking $booking,
    ): RedirectResponse|View {
        $booking->loadMissing('appointmentType');
        if ($payment->purpose === PaymentPurpose::Initial
            && $booking->status->value === 'confirmed'
            && filled($booking->appointmentType->redirect_url)) {
            $request->session()->forget('payment_manage_tokens.'.$payment->uuid);

            return redirect()->away($booking->appointmentType->redirect_url);
        }

        return $this->finish($request, $payment, 'success', 'Payment received.');
    }

    private function authorizeManageToken(Booking $booking, string $token): void
    {
        abort_unless(hash_equals($booking->manage_token_hash, hash('sha256', $token, true)), 404);
    }

    private function refundIfCancelled(Booking $booking, PaymentTransaction $payment, PaymentRefundService $refunds): void
    {
        if (in_array($booking->status->value, ['cancelled', 'declined'], true)) {
            $refunds->refundTransactionBalance($payment, 'Automatic refund: payment completed after booking cancellation.');

            return;
        }

        $refunds->refundOverpayment($payment);
    }
}
