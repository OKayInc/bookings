<?php
namespace App\Domain\Webhooks;

use App\Domain\Plans\PlanEntitlementService;
use App\Models\Organization;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class WebhookPublisher
{
    public const EVENTS = ['booking.created', 'booking.confirmed', 'booking.cancelled', 'booking.declined',
        'booking.rescheduled', 'payment.succeeded', 'refund.succeeded', 'ticket.checked_in'];

    public function publish(Organization $organization, string $event, array $data, ?WebhookEndpoint $only = null): void
    {
        if (! app(PlanEntitlementService::class)->hasBusinessFeatures($organization)) { return; }
        if (! in_array($event, [...self::EVENTS, 'webhook.test'], true)) { throw new \InvalidArgumentException('Unsupported webhook event.'); }
        $eventId = (string) Str::uuid7();
        $payload = json_encode(['id' => $eventId, 'type' => $event, 'api_version' => '2026-09-18',
            'created_at' => now('UTC')->toIso8601String(), 'organization_id' => $organization->uuid, 'data' => $data], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        // No HTTP here. Existing business transactions include these durable outbox rows.
        DB::transaction(function () use ($organization, $event, $only, $eventId, $payload): void {
            $query = WebhookEndpoint::where('organization_id', $organization->getKey())->where('is_active', true);
            if ($only) { $query->whereKey($only->getKey()); }
            foreach ($query->get() as $endpoint) {
                if ($event !== 'webhook.test' && ! in_array($event, $endpoint->events, true)) { continue; }
                WebhookDelivery::create(['organization_id' => $organization->getKey(), 'webhook_endpoint_id' => $endpoint->getKey(),
                    'event_id' => $eventId, 'event_type' => $event, 'endpoint_version' => $endpoint->version,
                    'payload' => $payload, 'available_at' => now('UTC')]);
            }
        });
    }
}
