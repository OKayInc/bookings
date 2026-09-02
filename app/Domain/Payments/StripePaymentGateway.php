<?php

namespace App\Domain\Payments;

use App\Models\OrganizationPaymentSetting;
use App\Models\PaymentRefund;
use App\Models\PaymentTransaction;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class StripePaymentGateway
{
    /** @return array{id:string,url:string,status:string,payment_intent:?string,payload:array<string,mixed>} */
    public function createCheckout(
        OrganizationPaymentSetting $settings,
        PaymentTransaction $payment,
        string $successUrl,
        string $cancelUrl,
    ): array {
        $booking = $payment->booking()->with('appointmentType')->firstOrFail();
        $response = $this->request($settings)
            ->withHeaders(['Idempotency-Key' => $payment->idempotency_key])
            ->asForm()
            ->post($this->url('/v1/checkout/sessions'), [
                'mode' => 'payment',
                'client_reference_id' => $payment->uuid,
                'customer_email' => $booking->email,
                'success_url' => $successUrl,
                'cancel_url' => $cancelUrl,
                'line_items' => [[
                    'quantity' => 1,
                    'price_data' => [
                        'currency' => strtolower($payment->currency),
                        'unit_amount' => (int) $payment->amount_minor,
                        'product_data' => [
                            'name' => $booking->appointmentType->name.' — '.$payment->purpose->label(),
                        ],
                    ],
                ]],
                'metadata' => [
                    'payment_uuid' => $payment->uuid,
                    'booking_uuid' => $booking->uuid,
                    'organization_uuid' => $booking->organization->uuid,
                ],
                'payment_intent_data' => [
                    'receipt_email' => $booking->email,
                    'metadata' => [
                        'payment_uuid' => $payment->uuid,
                        'booking_uuid' => $booking->uuid,
                    ],
                ],
            ]);
        $payload = $this->payload($response, 'Stripe could not create the checkout session.');

        if (! is_string($payload['id'] ?? null) || ! is_string($payload['url'] ?? null)) {
            throw new PaymentGatewayException('Stripe returned an incomplete checkout session.');
        }

        return [
            'id' => $payload['id'],
            'url' => $payload['url'],
            'status' => (string) ($payload['status'] ?? 'open'),
            'payment_intent' => is_string($payload['payment_intent'] ?? null) ? $payload['payment_intent'] : null,
            'payload' => $payload,
        ];
    }

    /** @return array<string,mixed> */
    public function retrieveCheckout(OrganizationPaymentSetting $settings, string $sessionId): array
    {
        return $this->payload(
            $this->request($settings)->get($this->url('/v1/checkout/sessions/'.rawurlencode($sessionId))),
            'Stripe could not verify the checkout session.',
        );
    }

    /** @return array<string,mixed> */
    public function refund(OrganizationPaymentSetting $settings, PaymentRefund $refund): array
    {
        $transaction = $refund->transaction;
        if (! $transaction->provider_capture_id) {
            throw new PaymentGatewayException('The Stripe PaymentIntent reference is missing.');
        }

        return $this->payload(
            $this->request($settings)
                ->withHeaders(['Idempotency-Key' => $refund->idempotency_key])
                ->asForm()
                ->post($this->url('/v1/refunds'), [
                    'payment_intent' => $transaction->provider_capture_id,
                    'amount' => (int) $refund->amount_minor,
                    'metadata' => [
                        'refund_uuid' => $refund->uuid,
                        'booking_uuid' => $refund->booking->uuid,
                    ],
                ]),
            'Stripe could not create the refund.',
        );
    }

    /** @return array<string,mixed> */
    public function verifyWebhook(OrganizationPaymentSetting $settings, string $payload, string $signatureHeader): array
    {
        $secret = (string) $settings->stripe_webhook_secret;
        $parts = [];
        foreach (explode(',', $signatureHeader) as $part) {
            [$key, $value] = array_pad(explode('=', trim($part), 2), 2, null);
            if ($key !== null && $value !== null) {
                $parts[$key][] = $value;
            }
        }

        $timestamp = isset($parts['t'][0]) ? (int) $parts['t'][0] : 0;
        $tolerance = max(0, (int) config('payments.stripe.webhook_tolerance_seconds', 300));
        if ($timestamp <= 0 || abs(CarbonImmutable::now('UTC')->timestamp - $timestamp) > $tolerance) {
            throw new PaymentGatewayException('The Stripe webhook timestamp is invalid or expired.');
        }

        $expected = hash_hmac('sha256', $timestamp.'.'.$payload, $secret);
        $valid = collect($parts['v1'] ?? [])->contains(fn (string $signature): bool => hash_equals($expected, $signature));
        if (! $valid) {
            throw new PaymentGatewayException('The Stripe webhook signature is invalid.');
        }

        $event = json_decode($payload, true);
        if (! is_array($event) || ! is_string($event['id'] ?? null) || ! is_string($event['type'] ?? null)) {
            throw new PaymentGatewayException('The Stripe webhook payload is invalid.');
        }

        return $event;
    }

    private function request(OrganizationPaymentSetting $settings): \Illuminate\Http\Client\PendingRequest
    {
        return Http::acceptJson()
            ->withBasicAuth((string) $settings->stripe_secret_key, '')
            ->timeout((int) config('payments.request_timeout_seconds', 20));
    }

    private function url(string $path): string
    {
        return rtrim((string) config('payments.stripe.api_url'), '/').$path;
    }

    /** @return array<string,mixed> */
    private function payload(Response $response, string $fallback): array
    {
        $payload = $response->json();
        if (! $response->successful() || ! is_array($payload)) {
            $message = is_array($payload) ? data_get($payload, 'error.message') : null;
            throw new PaymentGatewayException(is_string($message) && $message !== '' ? $message : $fallback);
        }

        return $payload;
    }
}
