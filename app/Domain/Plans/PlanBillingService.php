<?php

namespace App\Domain\Plans;

use App\Enums\OrganizationPlanTier;
use App\Models\Organization;
use App\Models\OrganizationPlanSubscription;
use App\Models\PlanWebhookEvent;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PlanBillingService
{
    public function __construct(private readonly PlanAuditService $audit) {}

    /** @param array<string,mixed> $event */
    public function processStripeEvent(array $event): void
    {
        DB::transaction(function () use ($event): void {
            $eventId = (string) $event['id'];
            if (PlanWebhookEvent::query()->where('provider_event_id', $eventId)->lockForUpdate()->exists()) {
                return;
            }

            $type = (string) $event['type'];
            $object = data_get($event, 'data.object');
            if (! is_array($object)) {
                throw new PlanBillingException('Stripe billing webhook data is incomplete.');
            }

            if ($type === 'checkout.session.completed') {
                $this->checkoutCompleted($object);
            } elseif (str_starts_with($type, 'customer.subscription.')) {
                $this->subscriptionChanged($object, $type === 'customer.subscription.deleted');
            } elseif ($type === 'invoice.payment_failed') {
                $this->invoiceStatus($object, false);
            } elseif ($type === 'invoice.paid') {
                $this->invoiceStatus($object, true);
            }

            PlanWebhookEvent::create([
                'provider_event_id' => $eventId,
                'event_type' => $type,
                'processed_at_utc' => now('UTC'),
            ]);
        }, 3);
    }

    /** @param array<string,mixed> $session */
    private function checkoutCompleted(array $session): void
    {
        $organization = $this->organizationFromMetadata($session);
        if ($organization === null) {
            return;
        }

        $subscriptionId = is_string($session['subscription'] ?? null) ? $session['subscription'] : null;
        $customerId = is_string($session['customer'] ?? null) ? $session['customer'] : null;
        if ($subscriptionId === null || $customerId === null) {
            throw new PlanBillingException('Stripe checkout did not include its customer and subscription references.');
        }
        $checkoutSessionId = is_string($session['id'] ?? null) ? $session['id'] : null;
        if ($checkoutSessionId === null) {
            throw new PlanBillingException('Stripe checkout did not include its session reference.');
        }

        $interval = (string) data_get($session, 'metadata.billing_interval', 'monthly');
        if (! in_array($interval, ['monthly', 'annual'], true)) {
            $interval = 'monthly';
        }
        $subscription = OrganizationPlanSubscription::query()
            ->where('organization_id', $organization->getKey())
            ->lockForUpdate()
            ->first();
        if ($subscription?->checkout_session_id !== null
            && ! hash_equals($subscription->checkout_session_id, $checkoutSessionId)) {
            // A newer checkout was started for this organization. Recording
            // this webhook as processed is safe, but its stale state is not.
            return;
        }
        $sameAuthoritativeSubscription = $subscription !== null
            && hash_equals((string) $subscription->provider_subscription_id, $subscriptionId)
            && $subscription->status !== 'incomplete';

        $values = [
            'provider' => 'stripe',
            'provider_customer_id' => $customerId,
            'provider_subscription_id' => $subscriptionId,
            'checkout_session_id' => $checkoutSessionId,
            'billing_interval' => $interval,
        ];
        if (! $sameAuthoritativeSubscription) {
            $values += [
                'status' => 'incomplete',
                'trial_ends_at_utc' => null,
                'current_period_ends_at_utc' => null,
                'grace_ends_at_utc' => null,
                'cancel_at_period_end' => false,
            ];
        }

        $subscription = OrganizationPlanSubscription::query()->updateOrCreate(
            ['organization_id' => $organization->getKey()],
            $values,
        );
        $this->syncTier($organization, $subscription);
        $this->audit->record('subscription.checkout_completed', $organization, details: [
            'subscription_uuid' => $subscription->uuid,
            'billing_interval' => $subscription->billing_interval,
            'status' => $subscription->status,
        ]);
    }

    /** @param array<string,mixed> $remote */
    private function subscriptionChanged(array $remote, bool $deleted): void
    {
        $providerId = is_string($remote['id'] ?? null) ? $remote['id'] : null;
        if ($providerId === null) {
            throw new PlanBillingException('Stripe subscription data is missing its subscription reference.');
        }
        $subscription = OrganizationPlanSubscription::query()
            ->where('provider_subscription_id', $providerId)->first();
        $organization = $subscription?->organization ?? $this->organizationFromMetadata($remote);
        if ($organization === null) {
            return;
        }

        $current = $organization->planSubscription()->lockForUpdate()->first();
        if ($subscription === null
            && $current?->provider_subscription_id !== null
            && ! hash_equals($current->provider_subscription_id, $providerId)
            && in_array($current->status, ['trialing', 'active', 'past_due'], true)) {
            // Stripe may deliver deletion or update events for a superseded
            // subscription after a replacement is already authoritative.
            return;
        }
        $subscription ??= $current;

        $status = $deleted ? 'cancelled' : (string) ($remote['status'] ?? 'incomplete');
        if (! in_array($status, [
            'incomplete', 'incomplete_expired', 'trialing', 'active', 'past_due',
            'canceled', 'cancelled', 'unpaid', 'paused',
        ], true)) {
            $status = 'incomplete';
        }
        $interval = (string) data_get($remote, 'metadata.billing_interval', $subscription?->billing_interval ?? 'monthly');
        if (! in_array($interval, ['monthly', 'annual'], true)) {
            $interval = 'monthly';
        }
        $subscription = OrganizationPlanSubscription::query()->updateOrCreate(
            ['organization_id' => $organization->getKey()],
            [
                'provider' => 'stripe',
                'provider_customer_id' => $this->stringId($remote['customer'] ?? null)
                    ?? $subscription?->provider_customer_id,
                'provider_subscription_id' => $providerId,
                'status' => $status,
                'billing_interval' => $interval,
                'trial_ends_at_utc' => $this->timestamp($remote['trial_end'] ?? null),
                'current_period_ends_at_utc' => $this->timestamp(
                    $remote['current_period_end'] ?? $this->latestItemPeriodEnd($remote),
                ),
                'grace_ends_at_utc' => $status === 'past_due'
                    ? ($subscription?->grace_ends_at_utc ?? now('UTC')->addDays((int) config('plans.billing_grace_days', 3)))
                    : null,
                'cancel_at_period_end' => (bool) ($remote['cancel_at_period_end'] ?? false),
            ],
        );
        $this->syncTier($organization, $subscription);
        $this->audit->record('subscription.updated', $organization, details: [
            'status' => $status,
            'cancel_at_period_end' => $subscription->cancel_at_period_end,
        ]);
    }

    /** @param array<string,mixed> $invoice */
    private function invoiceStatus(array $invoice, bool $paid): void
    {
        $providerId = $this->stringId($invoice['subscription'] ?? data_get($invoice, 'parent.subscription_details.subscription'));
        if ($providerId === null) {
            return;
        }
        $subscription = OrganizationPlanSubscription::query()
            ->where('provider_subscription_id', $providerId)->first();
        if ($subscription === null) {
            return;
        }
        if ($paid) {
            $updates = ['grace_ends_at_utc' => null];
            // A recovery invoice can restore a past-due subscription, but a
            // late/final invoice must never resurrect cancelled or unpaid access.
            if ($subscription->status === 'past_due') {
                $updates['status'] = 'active';
            }
        } elseif (in_array($subscription->status, ['trialing', 'active', 'past_due'], true)) {
            $updates = [
                'status' => 'past_due',
                'grace_ends_at_utc' => now('UTC')->addDays((int) config('plans.billing_grace_days', 3)),
            ];
        } else {
            $updates = [];
        }
        if ($updates !== []) {
            $subscription->update($updates);
        }
        $organization = $subscription->organization;
        $this->syncTier($organization, $subscription->fresh());
        $this->audit->record($paid ? 'invoice.paid' : 'invoice.payment_failed', $organization, details: [
            'invoice_id' => $this->stringId($invoice['id'] ?? null),
        ]);
    }

    private function syncTier(Organization $organization, OrganizationPlanSubscription $subscription): void
    {
        $organization->forceFill([
            'plan_tier' => $subscription->providesBusinessAccess()
                ? OrganizationPlanTier::Paid->value
                : OrganizationPlanTier::Free->value,
        ])->save();
    }

    /** @param array<string,mixed> $object */
    private function organizationFromMetadata(array $object): ?Organization
    {
        $uuid = (string) data_get($object, 'metadata.organization_uuid', '');
        if (! Str::isUuid($uuid)) {
            return null;
        }

        return Organization::whereUuid($uuid)->first();
    }

    private function timestamp(mixed $value): ?CarbonImmutable
    {
        return is_numeric($value) && (int) $value > 0
            ? CarbonImmutable::createFromTimestampUTC((int) $value)
            : null;
    }

    private function stringId(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }

    /** @param array<string,mixed> $subscription */
    private function latestItemPeriodEnd(array $subscription): ?int
    {
        $ends = collect((array) data_get($subscription, 'items.data', []))
            ->pluck('current_period_end')
            ->filter(fn ($value): bool => is_numeric($value) && (int) $value > 0)
            ->map(fn ($value): int => (int) $value);

        return $ends->isEmpty() ? null : $ends->max();
    }
}
