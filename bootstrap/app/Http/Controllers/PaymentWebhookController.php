<?php

namespace App\Http\Controllers;

use App\Domain\Payments\PayPalPaymentGateway;
use App\Domain\Payments\PaymentGatewayException;
use App\Domain\Payments\PaymentWebhookService;
use App\Domain\Payments\StripePaymentGateway;
use App\Enums\PaymentProvider;
use App\Models\Organization;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PaymentWebhookController extends Controller
{
    public function __invoke(
        Request $request,
        Organization $organization,
        string $provider,
        StripePaymentGateway $stripe,
        PayPalPaymentGateway $paypal,
        PaymentWebhookService $webhooks,
    ): Response {
        $resolved = PaymentProvider::tryFrom($provider);
        abort_if($resolved === null, 404);
        $settings = $organization->paymentSettings;
        abort_unless($settings?->hasCredentials($resolved), 404);
        $raw = $request->getContent();

        try {
            $payload = match ($resolved) {
                PaymentProvider::Stripe => $stripe->verifyWebhook(
                    $settings,
                    $raw,
                    (string) $request->header('Stripe-Signature', ''),
                ),
                PaymentProvider::PayPal => $paypal->verifyWebhook($settings, [
                    'paypal-auth-algo' => $request->header('PayPal-Auth-Algo'),
                    'paypal-cert-url' => $request->header('PayPal-Cert-Url'),
                    'paypal-transmission-id' => $request->header('PayPal-Transmission-Id'),
                    'paypal-transmission-sig' => $request->header('PayPal-Transmission-Sig'),
                    'paypal-transmission-time' => $request->header('PayPal-Transmission-Time'),
                ], $raw),
            };
            $webhooks->process($organization, $resolved, $payload);
        } catch (PaymentGatewayException $exception) {
            return response($exception->getMessage(), 400);
        }

        return response()->noContent();
    }
}
