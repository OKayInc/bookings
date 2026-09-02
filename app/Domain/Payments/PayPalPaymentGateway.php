<?php

namespace App\Domain\Payments;

use App\Domain\Money\MoneyService;
use App\Models\OrganizationPaymentSetting;
use App\Models\PaymentRefund;
use App\Models\PaymentTransaction;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class PayPalPaymentGateway
{
    public function __construct(private readonly MoneyService $money)
    {
    }

    /** @return array{id:string,url:string,status:string,payload:array<string,mixed>} */
    public function createCheckout(
        OrganizationPaymentSetting $settings,
        PaymentTransaction $payment,
        string $returnUrl,
        string $cancelUrl,
    ): array {
        $booking = $payment->booking()->with('appointmentType')->firstOrFail();
        $response = $this->request($settings)
            ->withHeaders(['PayPal-Request-Id' => $payment->idempotency_key, 'Prefer' => 'return=representation'])
            ->post($this->url($settings, '/v2/checkout/orders'), [
                'intent' => 'CAPTURE',
                'purchase_units' => [[
                    'reference_id' => $payment->uuid,
                    'custom_id' => $booking->uuid,
                    'invoice_id' => $booking->reference.'-'.Str::upper(Str::substr(str_replace('-', '', $payment->uuid), -12)),
                    'description' => Str::limit($booking->appointmentType->name.' — '.$payment->purpose->label(), 127, ''),
                    'amount' => [
                        'currency_code' => Str::upper($payment->currency),
                        'value' => $this->money->decimal((int) $payment->amount_minor, $payment->currency),
                    ],
                ]],
                'payment_source' => [
                    'paypal' => [
                        'experience_context' => [
                            'user_action' => 'PAY_NOW',
                            'return_url' => $returnUrl,
                            'cancel_url' => $cancelUrl,
                            'brand_name' => Str::limit($booking->organization->name, 127, ''),
                        ],
                    ],
                ],
            ]);
        $payload = $this->payload($response, 'PayPal could not create the order.');
        $approve = collect($payload['links'] ?? [])->first(fn ($link): bool => is_array($link) && ($link['rel'] ?? null) === 'payer-action')
            ?? collect($payload['links'] ?? [])->first(fn ($link): bool => is_array($link) && ($link['rel'] ?? null) === 'approve');

        if (! is_string($payload['id'] ?? null) || ! is_array($approve) || ! is_string($approve['href'] ?? null)) {
            throw new PaymentGatewayException('PayPal returned an incomplete order.');
        }

        return [
            'id' => $payload['id'],
            'url' => $approve['href'],
            'status' => (string) ($payload['status'] ?? 'CREATED'),
            'payload' => $payload,
        ];
    }

    /** @return array<string,mixed> */
    public function capture(OrganizationPaymentSetting $settings, string $orderId, string $idempotencyKey): array
    {
        return $this->payload(
            $this->request($settings)
                ->withHeaders(['PayPal-Request-Id' => $idempotencyKey.'-capture', 'Prefer' => 'return=representation'])
                ->post($this->url($settings, '/v2/checkout/orders/'.rawurlencode($orderId).'/capture')),
            'PayPal could not capture the approved order.',
        );
    }

    /** @return array<string,mixed> */
    public function refund(OrganizationPaymentSetting $settings, PaymentRefund $refund): array
    {
        $transaction = $refund->transaction;
        if (! $transaction->provider_capture_id) {
            throw new PaymentGatewayException('The PayPal capture reference is missing.');
        }

        return $this->payload(
            $this->request($settings)
                ->withHeaders(['PayPal-Request-Id' => $refund->idempotency_key, 'Prefer' => 'return=representation'])
                ->post($this->url($settings, '/v2/payments/captures/'.rawurlencode($transaction->provider_capture_id).'/refund'), [
                    'amount' => [
                        'currency_code' => Str::upper($refund->currency),
                        'value' => $this->money->decimal((int) $refund->amount_minor, $refund->currency),
                    ],
                    'note_to_payer' => Str::limit((string) ($refund->reason ?: 'Booking refund'), 255, ''),
                ]),
            'PayPal could not create the refund.',
        );
    }

    /** @param array<string,string|null> $headers
     *  @return array<string,mixed>
     */
    public function verifyWebhook(OrganizationPaymentSetting $settings, array $headers, string $rawPayload): array
    {
        $event = json_decode($rawPayload, true);
        if (! is_array($event) || ! is_string($event['id'] ?? null) || ! is_string($event['event_type'] ?? null)) {
            throw new PaymentGatewayException('The PayPal webhook payload is invalid.');
        }

        $verification = $this->payload(
            $this->request($settings)->post($this->url($settings, '/v1/notifications/verify-webhook-signature'), [
                'auth_algo' => $headers['paypal-auth-algo'] ?? null,
                'cert_url' => $headers['paypal-cert-url'] ?? null,
                'transmission_id' => $headers['paypal-transmission-id'] ?? null,
                'transmission_sig' => $headers['paypal-transmission-sig'] ?? null,
                'transmission_time' => $headers['paypal-transmission-time'] ?? null,
                'webhook_id' => $settings->paypal_webhook_id,
                'webhook_event' => $event,
            ]),
            'PayPal could not verify the webhook signature.',
        );

        if (($verification['verification_status'] ?? null) !== 'SUCCESS') {
            throw new PaymentGatewayException('The PayPal webhook signature is invalid.');
        }

        return $event;
    }

    private function request(OrganizationPaymentSetting $settings): \Illuminate\Http\Client\PendingRequest
    {
        return Http::acceptJson()
            ->withToken($this->accessToken($settings))
            ->timeout((int) config('payments.request_timeout_seconds', 20));
    }

    private function accessToken(OrganizationPaymentSetting $settings): string
    {
        $cacheKey = 'paypal-token:'.$settings->uuid.':'.($settings->paypal_sandbox ? 'sandbox' : 'live');

        return Cache::remember($cacheKey, now()->addMinutes(5), function () use ($settings): string {
            $response = Http::asForm()
                ->withBasicAuth((string) $settings->paypal_client_id, (string) $settings->paypal_client_secret)
                ->timeout((int) config('payments.request_timeout_seconds', 20))
                ->post($this->url($settings, '/v1/oauth2/token'), ['grant_type' => 'client_credentials']);
            $payload = $this->payload($response, 'PayPal authentication failed.');
            if (! is_string($payload['access_token'] ?? null)) {
                throw new PaymentGatewayException('PayPal did not return an access token.');
            }

            return $payload['access_token'];
        });
    }

    private function url(OrganizationPaymentSetting $settings, string $path): string
    {
        $base = $settings->paypal_sandbox
            ? config('payments.paypal.sandbox_api_url')
            : config('payments.paypal.live_api_url');

        return rtrim((string) $base, '/').$path;
    }

    /** @return array<string,mixed> */
    private function payload(Response $response, string $fallback): array
    {
        $payload = $response->json();
        if (! $response->successful() || ! is_array($payload)) {
            $message = is_array($payload) ? ($payload['message'] ?? data_get($payload, 'details.0.description')) : null;
            throw new PaymentGatewayException(is_string($message) && $message !== '' ? $message : $fallback);
        }

        return $payload;
    }
}
