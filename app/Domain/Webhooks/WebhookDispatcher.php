<?php
namespace App\Domain\Webhooks;

use App\Domain\Plans\PlanEntitlementService;
use App\Models\WebhookAttempt;
use App\Models\WebhookDelivery;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class WebhookDispatcher
{
    public const DELAYS = [60, 300, 1800, 7200, 21600];

    public function deliver(string $id): bool
    {
        $claim = DB::transaction(function () use ($id): ?array {
            $delivery = WebhookDelivery::whereKey($id)->lockForUpdate()->first();
            if (! $delivery) { return null; }
            $due = $delivery->status === 'pending' && $delivery->available_at->lte(now('UTC'));
            $stale = $delivery->status === 'delivering' && $delivery->claimed_at?->lte(now('UTC')->subMinutes(2));
            if (! $due && ! $stale) { return null; }
            $endpoint = $delivery->endpoint()->first();
            if (! $endpoint || ! $endpoint->is_active || $endpoint->version !== $delivery->endpoint_version
                || ! app(PlanEntitlementService::class)->hasBusinessFeatures($endpoint->organization)) {
                $delivery->update(['status' => 'skipped', 'last_error' => 'Endpoint disabled, changed or plan ineligible.', 'claim_token' => null]);
                return null;
            }
            if ($delivery->cycle_attempts >= 6) {
                $delivery->update(['status' => 'failed', 'last_error' => 'Attempt limit reached after worker interruption.', 'claim_token' => null]);
                return null;
            }
            $token = (string) Str::uuid7();
            $delivery->update(['status' => 'delivering', 'claimed_at' => now('UTC'), 'claim_token' => $token,
                'attempts' => $delivery->attempts + 1, 'cycle_attempts' => $delivery->cycle_attempts + 1]);
            return [$delivery, $endpoint->url, $endpoint->secret, $token];
        });
        if (! $claim) { return false; }
        [$delivery, $url, $secret, $token] = $claim;
        $timestamp = (string) now('UTC')->timestamp;
        $signature = WebhookSignature::sign($delivery->payload, $secret, (int) $timestamp);
        $started = microtime(true); $status = null; $error = null;
        try {
            $status = app(WebhookTransport::class)->send($url, $delivery->payload, [
                'Content-Type: application/json', 'User-Agent: Appointment.to-Webhooks/1.0',
                'X-Appointment-Event: '.$delivery->event_type, 'X-Appointment-Event-Id: '.$delivery->event_id,
                'X-Appointment-Delivery-Id: '.$delivery->uuid,
                'X-Appointment-Signature: '.$signature,
            ]);
            if ($status < 200 || $status >= 300) { $error = 'Destination returned HTTP '.$status.'.'; }
        } catch (\Throwable $e) {
            // Never store URLs, secrets, response bodies or request headers in an error message.
            $error = 'Delivery failed: check destination DNS, TLS, availability and server cURL support.';
        }
        $duration = (int) round((microtime(true) - $started) * 1000);
        DB::transaction(function () use ($delivery, $token, $status, $error, $duration): void {
            $current = WebhookDelivery::whereKey($delivery->getKey())->lockForUpdate()->first();
            if (! $current || $current->claim_token !== $token) { return; }
            WebhookAttempt::create(['webhook_delivery_id' => $current->getKey(), 'number' => $current->attempts,
                'http_status' => $status, 'error' => $error, 'duration_ms' => $duration]);
            $success = $error === null;
            $retry = ! $success && $current->cycle_attempts < 6;
            $current->update(['status' => $success ? 'succeeded' : ($retry ? 'pending' : 'failed'),
                'http_status' => $status, 'last_error' => $error, 'claim_token' => null, 'claimed_at' => null,
                'delivered_at' => $success ? now('UTC') : null,
                'available_at' => now('UTC')->addSeconds($retry ? self::DELAYS[$current->cycle_attempts - 1] : 0)]);
        });
        return true;
    }
}
