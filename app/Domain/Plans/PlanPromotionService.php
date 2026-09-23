<?php

namespace App\Domain\Plans;

use App\Models\Organization;
use App\Models\OrganizationPlanGrant;
use App\Models\PlanPromotionCode;
use App\Models\PlanPromotionRedemption;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PlanPromotionService
{
    public function __construct(private readonly PlanAuditService $audit) {}

    /** @return array{code:PlanPromotionCode,plaintext:string} */
    public function create(User $actor, ?int $maxRedemptions = null, ?\DateTimeInterface $expiresAt = null): array
    {
        $plaintext = 'FOREVER-'.strtoupper(Str::random(8)).'-'.strtoupper(Str::random(8));
        $normalized = $this->normalize($plaintext);
        $code = PlanPromotionCode::create([
            'created_by_user_id' => $actor->getKey(),
            'code_hash' => hash('sha256', $normalized, true),
            'code_hint' => '…'.substr($normalized, -6),
            'max_redemptions' => $maxRedemptions,
            'redemption_count' => 0,
            'expires_at_utc' => $expiresAt,
            'is_active' => true,
        ]);
        $this->audit->record('promotion.created', actor: $actor, details: [
            'code_uuid' => $code->uuid,
            'max_redemptions' => $maxRedemptions,
            'expires_at_utc' => $expiresAt?->format(DATE_ATOM),
        ]);

        return ['code' => $code, 'plaintext' => $plaintext];
    }

    public function redeem(Organization $organization, User $user, string $plaintext): OrganizationPlanGrant
    {
        $normalized = $this->normalize($plaintext);
        if ($normalized === '') {
            throw new PlanLimitException('Enter a valid complimentary plan code.');
        }

        return DB::transaction(function () use ($organization, $user, $normalized): OrganizationPlanGrant {
            $code = PlanPromotionCode::query()
                ->where('code_hash', hash('sha256', $normalized, true))
                ->lockForUpdate()
                ->first();
            if ($code === null || ! $code->isRedeemable()) {
                throw new PlanLimitException('This complimentary plan code is invalid, expired, disabled, or fully redeemed.');
            }

            if (PlanPromotionRedemption::query()
                ->where('promotion_code_id', $code->getKey())
                ->where('organization_id', $organization->getKey())
                ->exists()) {
                throw new PlanLimitException('This organization has already redeemed this code.');
            }

            PlanPromotionRedemption::create([
                'promotion_code_id' => $code->getKey(),
                'organization_id' => $organization->getKey(),
                'redeemed_by_user_id' => $user->getKey(),
            ]);
            $code->increment('redemption_count');
            $grant = OrganizationPlanGrant::create([
                'organization_id' => $organization->getKey(),
                'promotion_code_id' => $code->getKey(),
                'granted_by_user_id' => null,
                'source' => 'promotion',
                'reason' => 'Forever-free promotion code '.$code->code_hint,
                'starts_at_utc' => now('UTC'),
                'ends_at_utc' => null,
                'revoked_at_utc' => null,
            ]);
            $this->audit->record('promotion.redeemed', $organization, $user, [
                'code_uuid' => $code->uuid,
                'grant_uuid' => $grant->uuid,
            ]);

            return $grant;
        }, 3);
    }

    public function grant(Organization $organization, User $actor, ?string $reason = null): OrganizationPlanGrant
    {
        return DB::transaction(function () use ($organization, $actor, $reason): OrganizationPlanGrant {
            Organization::query()->whereKey($organization->getKey())->lockForUpdate()->firstOrFail();

            $existing = $organization->planGrants()
                ->whereNull('revoked_at_utc')
                ->where('starts_at_utc', '<=', now('UTC'))
                ->where(fn ($query) => $query->whereNull('ends_at_utc')->orWhere('ends_at_utc', '>', now('UTC')))
                ->latest('starts_at_utc')
                ->first();
            if ($existing !== null) {
                return $existing;
            }

            $grant = OrganizationPlanGrant::create([
                'organization_id' => $organization->getKey(),
                'promotion_code_id' => null,
                'granted_by_user_id' => $actor->getKey(),
                'source' => 'owner',
                'reason' => filled($reason) ? trim((string) $reason) : 'Granted by the platform owner',
                'starts_at_utc' => now('UTC'),
                'ends_at_utc' => null,
                'revoked_at_utc' => null,
            ]);
            $this->audit->record('complimentary.granted', $organization, $actor, [
                'grant_uuid' => $grant->uuid,
                'reason' => $grant->reason,
            ]);

            return $grant;
        }, 3);
    }

    public function revoke(OrganizationPlanGrant $grant, User $actor): void
    {
        DB::transaction(function () use ($grant, $actor): void {
            $locked = OrganizationPlanGrant::query()->whereKey($grant->getKey())->lockForUpdate()->firstOrFail();
            if ($locked->revoked_at_utc !== null) {
                return;
            }
            $locked->update(['revoked_at_utc' => now('UTC')]);
            $this->audit->record('complimentary.revoked', $locked->organization, $actor, [
                'grant_uuid' => $locked->uuid,
                'source' => $locked->source,
            ]);
        }, 3);
    }

    public function disableCode(PlanPromotionCode $code, User $actor): void
    {
        $code->update(['is_active' => false]);
        $this->audit->record('promotion.disabled', actor: $actor, details: ['code_uuid' => $code->uuid]);
    }

    private function normalize(string $code): string
    {
        return strtoupper(preg_replace('/\s+/', '', trim($code)) ?? '');
    }
}
