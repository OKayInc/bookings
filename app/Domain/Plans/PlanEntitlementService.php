<?php

namespace App\Domain\Plans;

use App\Enums\OrganizationPlanTier;
use App\Enums\PlanLevel;
use App\Models\Organization;
use Carbon\CarbonImmutable;

class PlanEntitlementService
{
    public function for(Organization $organization): PlanEntitlement
    {
        $uuid = strtolower((string) $organization->uuid);
        if ($uuid !== '' && in_array($uuid, (array) config('plans.complimentary_organization_ids', []), true)) {
            return new PlanEntitlement(PlanLevel::Complimentary, 'environment');
        }

        $grant = $organization->planGrants()
            ->whereNull('revoked_at_utc')
            ->where('starts_at_utc', '<=', now('UTC'))
            ->where(fn ($query) => $query->whereNull('ends_at_utc')->orWhere('ends_at_utc', '>', now('UTC')))
            ->latest('starts_at_utc')
            ->first();
        if ($grant !== null) {
            return new PlanEntitlement(
                PlanLevel::Complimentary,
                $grant->source,
                $grant->ends_at_utc === null ? null : CarbonImmutable::instance($grant->ends_at_utc)->utc(),
            );
        }

        $subscription = $organization->planSubscription()->first();
        if ($subscription !== null && $subscription->providesBusinessAccess()) {
            $expiresAt = $subscription->status === 'trialing'
                ? $subscription->trial_ends_at_utc
                : ($subscription->status === 'past_due' ? $subscription->grace_ends_at_utc : null);

            return new PlanEntitlement(
                PlanLevel::Business,
                $subscription->status === 'trialing' ? 'trial' : 'subscription',
                $expiresAt === null ? null : CarbonImmutable::instance($expiresAt)->utc(),
            );
        }

        // Preserve organizations manually marked paid before M11. Once a billing
        // record exists, its status is authoritative and this compatibility path
        // can no longer keep a cancelled subscription active.
        if ($subscription === null && $organization->plan_tier === OrganizationPlanTier::Paid) {
            return new PlanEntitlement(PlanLevel::Business, 'legacy_paid');
        }

        return new PlanEntitlement(PlanLevel::Free, 'default');
    }

    public function hasBusinessFeatures(Organization $organization): bool
    {
        return $this->for($organization)->hasBusinessFeatures();
    }

    public function isComplimentary(Organization $organization): bool
    {
        return $this->for($organization)->level === PlanLevel::Complimentary;
    }
}
