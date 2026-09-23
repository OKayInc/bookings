<?php

namespace App\Domain\Plans;

use App\Enums\PlanAddon;
use App\Enums\PlanLevel;
use App\Models\Organization;
use App\Models\OrganizationPlanAddon;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class PlanAddonService
{
    public function __construct(
        private readonly PlanLimitService $limits,
        private readonly PlanEntitlementService $entitlements,
        private readonly PlanStripeGateway $stripe,
        private readonly PlanAuditService $audit,
    ) {}

    /** @return array<string,int> */
    public function quantities(Organization $organization): array
    {
        $records = $organization->planAddons()->get()->keyBy(fn (OrganizationPlanAddon $addon): string => $addon->addon->value);

        return collect(PlanAddon::cases())->mapWithKeys(fn (PlanAddon $addon): array => [
            $addon->value => $records->get($addon->value)?->effectiveQuantity() ?? 0,
        ])->all();
    }

    /** @param array<string,int> $requested */
    public function update(Organization $organization, User $actor, array $requested): void
    {
        $level = $this->entitlements->for($organization)->level;
        if ($level === PlanLevel::Complimentary) {
            throw new PlanLimitException('Complimentary Unlimited organizations do not need paid add-ons.');
        }

        $normalized = [];
        foreach (PlanAddon::cases() as $addon) {
            $normalized[$addon->value] = max(0, min(100000, (int) ($requested[$addon->value] ?? 0)));
        }
        if ($level === PlanLevel::Business) {
            $this->limits->assertAddonReductionAllowed($organization, $normalized);
        }

        $subscription = $organization->planSubscription()->first();
        $providerActive = $level === PlanLevel::Business
            && $subscription?->provider_subscription_id !== null
            && in_array($subscription->status, ['trialing', 'active', 'past_due'], true);
        $effectiveAt = $subscription?->current_period_ends_at_utc;
        if ($effectiveAt === null || $effectiveAt->isPast()) {
            $effectiveAt = $subscription?->billing_interval === 'annual'
                ? now('UTC')->addYear()
                : now('UTC')->addMonth();
        }

        $before = $organization->planAddons()->get()->mapWithKeys(fn (OrganizationPlanAddon $record): array => [
            $record->addon->value => $record->only(['quantity', 'pending_quantity', 'pending_effective_at_utc', 'provider_item_id']),
        ])->all();
        $paidThroughQuantities = collect(PlanAddon::cases())->mapWithKeys(fn (PlanAddon $addon): array => [
            $addon->value => max(0, (int) data_get($before, $addon->value.'.quantity', 0)),
        ])->all();

        DB::transaction(function () use ($organization, $normalized, $providerActive, $effectiveAt): void {
            Organization::query()->whereKey($organization->getKey())->lockForUpdate()->firstOrFail();
            foreach (PlanAddon::cases() as $addon) {
                $record = OrganizationPlanAddon::query()->firstOrNew([
                    'organization_id' => $organization->getKey(),
                    'addon' => $addon->value,
                ]);
                $current = $record->exists ? $record->effectiveQuantity() : 0;
                $desired = $normalized[$addon->value];

                if ($providerActive && $desired < $current) {
                    $record->fill([
                        'quantity' => $current,
                        'pending_quantity' => $desired,
                        'pending_effective_at_utc' => $effectiveAt,
                    ])->save();
                } else {
                    $record->fill([
                        'quantity' => $desired,
                        'pending_quantity' => null,
                        'pending_effective_at_utc' => null,
                    ])->save();
                }
            }
        }, 3);

        try {
            if ($providerActive) {
                // Send the requested billing quantity to Stripe now. Local
                // capacity reductions remain pending through the paid period,
                // but Stripe must know the next-period quantity before renewal.
                $providerItems = $this->stripe->syncAddons(
                    $organization->refresh(),
                    $normalized,
                    $paidThroughQuantities,
                );
                foreach ($providerItems as $addon => $itemId) {
                    $organization->planAddons()->where('addon', $addon)->update(['provider_item_id' => $itemId]);
                }
            }
        } catch (\Throwable $exception) {
            $this->restore($organization, $before);
            throw $exception;
        }

        $this->audit->record('addons.updated', $organization, $actor, ['requested' => $normalized]);
    }

    public function applyDue(Organization $organization): bool
    {
        $due = $organization->planAddons()
            ->whereNotNull('pending_quantity')
            ->where('pending_effective_at_utc', '<=', now('UTC'))
            ->get();
        if ($due->isEmpty()) {
            return false;
        }

        DB::transaction(function () use ($due): void {
            foreach ($due as $record) {
                $quantity = (int) $record->pending_quantity;
                $record->update([
                    'quantity' => $quantity,
                    'pending_quantity' => null,
                    'pending_effective_at_utc' => null,
                    'provider_item_id' => $quantity === 0 ? null : $record->provider_item_id,
                ]);
            }
        }, 3);
        $this->audit->record('addons.pending_applied', $organization, details: ['quantities' => $this->quantities($organization)]);

        return true;
    }

    private function restore(Organization $organization, array $before): void
    {
        DB::transaction(function () use ($organization, $before): void {
            foreach (PlanAddon::cases() as $addon) {
                if (! isset($before[$addon->value])) {
                    $organization->planAddons()->where('addon', $addon->value)->delete();

                    continue;
                }
                $organization->planAddons()->where('addon', $addon->value)->update($before[$addon->value]);
            }
        }, 3);
    }
}
