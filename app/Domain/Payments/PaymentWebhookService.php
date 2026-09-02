<?php

namespace App\Domain\Payments;

use App\Domain\Money\MoneyService;
use App\Enums\PaymentProvider;
use App\Enums\PaymentRefundStatus;
use App\Models\Organization;
use App\Models\PaymentRefund;
use App\Models\PaymentTransaction;
use App\Models\PaymentWebhookEvent;
use RuntimeException;

class PaymentWebhookService
{
    public function __construct(
        private readonly MoneyService $money,
        private readonly PaymentStateService $state,
        private readonly PaymentRefundService $refunds,
    ) {
    }

    /** @param array<string,mixed> $payload */
    public function process(Organization $organization, PaymentProvider $provider, array $payload): void
    {
        $eventId = (string) ($payload['id'] ?? '');
        $eventType = (string) ($payload[$provider === PaymentProvider::Stripe ? 'type' : 'event_type'] ?? '');
        if ($eventId === '' || $eventType === '') {
            throw new RuntimeException('The payment webhook has no event identifier or type.');
        }

        $event = PaymentWebhookEvent::firstOrCreate([
            'organization_id' => $organization->getKey(),
            'provider' => $provider->value,
            'provider_event_id' => $eventId,
        ], [
            'event_type' => $eventType,
            'payload' => $payload,
        ]);
        if ($event->processed_at_utc !== null) {
            return;
        }

        try {
            match ($provider) {
                PaymentProvider::Stripe => $this->processStripe($organization, $eventType, $payload),
                PaymentProvider::PayPal => $this->processPayPal($organization, $eventType, $payload),
            };
            $event->update(['processed_at_utc' => now('UTC'), 'processing_error' => null]);
        } catch (\Throwable $exception) {
            $event->update(['processing_error' => $exception->getMessage()]);
            throw $exception;
        }
    }

    /** @param array<string,mixed> $event */
    private function processStripe(Organization $organization, string $eventType, array $event): void
    {
        $object = data_get($event, 'data.object');
        if (! is_array($object)) {
            return;
        }

        if (in_array($eventType, ['checkout.session.completed', 'checkout.session.async_payment_succeeded'], true)) {
            if (($object['payment_status'] ?? null) !== 'paid') {
                return;
            }
            $payment = PaymentTransaction::query()
                ->where('organization_id', $organization->getKey())
                ->where('provider', PaymentProvider::Stripe->value)
                ->where('provider_external_id', (string) ($object['id'] ?? ''))
                ->firstOrFail();
            $this->state->complete(
                $payment,
                (string) ($object['payment_intent'] ?? $payment->provider_capture_id),
                (int) ($object['amount_total'] ?? 0),
                (string) ($object['currency'] ?? ''),
                $object,
            );
            $this->refundLateCapture($payment);
        } elseif (in_array($eventType, ['checkout.session.expired', 'checkout.session.async_payment_failed'], true)) {
            $payment = PaymentTransaction::query()
                ->where('organization_id', $organization->getKey())
                ->where('provider', PaymentProvider::Stripe->value)
                ->where('provider_external_id', (string) ($object['id'] ?? ''))
                ->first();
            if ($payment !== null) {
                $this->state->markFailed($payment, 'Stripe reported that checkout expired or payment failed.', $eventType === 'checkout.session.expired');
            }
        } elseif (str_starts_with($eventType, 'refund.')) {
            $status = strtolower((string) ($object['status'] ?? ''));
            $this->updateRefund(
                $organization,
                PaymentProvider::Stripe,
                (string) ($object['id'] ?? ''),
                $status === 'succeeded',
                in_array($status, ['failed', 'canceled', 'cancelled'], true),
                $object,
            );
        }
    }

    /** @param array<string,mixed> $event */
    private function processPayPal(Organization $organization, string $eventType, array $event): void
    {
        $resource = $event['resource'] ?? null;
        if (! is_array($resource)) {
            return;
        }

        if ($eventType === 'PAYMENT.CAPTURE.COMPLETED') {
            $orderId = (string) data_get($resource, 'supplementary_data.related_ids.order_id', '');
            $payment = PaymentTransaction::query()
                ->where('organization_id', $organization->getKey())
                ->where('provider', PaymentProvider::PayPal->value)
                ->where('provider_external_id', $orderId)
                ->firstOrFail();
            $currency = (string) data_get($resource, 'amount.currency_code', '');
            $amount = $this->money->parse((string) data_get($resource, 'amount.value', ''), $currency);
            $this->state->complete($payment, (string) ($resource['id'] ?? ''), $amount, $currency, $resource);
            $this->refundLateCapture($payment);
        } elseif ($eventType === 'PAYMENT.CAPTURE.DENIED') {
            $orderId = (string) data_get($resource, 'supplementary_data.related_ids.order_id', '');
            $payment = PaymentTransaction::query()
                ->where('organization_id', $organization->getKey())
                ->where('provider', PaymentProvider::PayPal->value)
                ->where('provider_external_id', $orderId)
                ->first();
            if ($payment !== null) {
                $this->state->markFailed($payment, 'PayPal denied the payment capture.');
            }
        } elseif (in_array($eventType, ['PAYMENT.CAPTURE.REFUNDED', 'PAYMENT.CAPTURE.REFUND.FAILED'], true)) {
            $this->updateRefund(
                $organization,
                PaymentProvider::PayPal,
                (string) ($resource['id'] ?? ''),
                $eventType === 'PAYMENT.CAPTURE.REFUNDED'
                    && strtoupper((string) ($resource['status'] ?? 'COMPLETED')) === 'COMPLETED',
                $eventType === 'PAYMENT.CAPTURE.REFUND.FAILED',
                $resource,
            );
        }
    }

    /** @param array<string,mixed> $payload */
    private function updateRefund(
        Organization $organization,
        PaymentProvider $provider,
        string $externalId,
        bool $succeeded,
        bool $failed,
        array $payload,
    ): void
    {
        if ($externalId === '') {
            return;
        }
        $refund = PaymentRefund::query()
            ->where('organization_id', $organization->getKey())
            ->where('provider', $provider->value)
            ->where('provider_refund_id', $externalId)
            ->first();
        if ($refund === null) {
            return;
        }
        if ($refund->status === PaymentRefundStatus::Succeeded) {
            return;
        }
        $refund->update([
            'status' => $succeeded
                ? PaymentRefundStatus::Succeeded->value
                : ($failed ? PaymentRefundStatus::Failed->value : PaymentRefundStatus::Pending->value),
            'provider_payload' => $payload,
            'failure_message' => $failed ? 'The provider reported that the refund failed.' : null,
            'completed_at_utc' => $succeeded || $failed ? now('UTC') : null,
        ]);
        $this->state->refresh($refund->booking);
    }

    private function refundLateCapture(PaymentTransaction $payment): void
    {
        $booking = $payment->booking()->firstOrFail();
        if (in_array($booking->status->value, ['cancelled', 'declined'], true)) {
            $this->refunds->refundTransactionBalance($payment, 'Automatic refund: payment completed after booking cancellation.');

            return;
        }

        $this->refunds->refundOverpayment($payment);
    }
}
