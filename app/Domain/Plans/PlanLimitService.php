<?php

namespace App\Domain\Plans;

use App\Enums\CalendarConnectionStatus;
use App\Enums\MembershipStatus;
use App\Enums\PlanAddon;
use App\Enums\PlanLevel;
use App\Models\AppointmentQuestion;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class PlanLimitService
{
    public function __construct(
        private readonly PlanEntitlementService $entitlements,
        private readonly PlanStorageService $storage,
        private readonly PlatformOwnerService $platformOwner,
    ) {}

    public function limit(Organization $organization, string $key, array $addonOverrides = []): ?int
    {
        $entitlement = $this->entitlements->for($organization);
        if ($entitlement->level === PlanLevel::Complimentary) {
            return null;
        }

        $level = $entitlement->level === PlanLevel::Business ? 'business' : 'free';
        $configured = config("plans.limits.{$level}.{$key}");
        if ($configured === null) {
            return null;
        }

        $limit = max(0, (int) $configured);
        // Pre-M11 manually paid organizations retain the included Business
        // feature set, but local add-on rows cannot create unbilled capacity.
        if ($level !== 'business' || $entitlement->source === 'legacy_paid') {
            return $limit;
        }

        foreach (PlanAddon::cases() as $addon) {
            if ($addon->limitKey() !== $key) {
                continue;
            }
            $limit += $this->addonQuantity($organization, $addon, $addonOverrides) * $addon->unitsPerQuantity();
        }

        if ($key === 'person_resources') {
            $limit += $this->addonQuantity($organization, PlanAddon::Members, $addonOverrides);
        }

        return $limit;
    }

    public function usage(Organization $organization): array
    {
        $month = app(PlanUsageService::class)->current($organization);

        return [
            'members' => $organization->memberships()->where('status', MembershipStatus::Active->value)->count(),
            'active_appointment_types' => $organization->appointmentTypes()->where('is_active', true)->count(),
            'monthly_bookings' => $month->booking_count,
            'resources' => $organization->resources()->where('resources.is_active', true)->count(),
            'person_resources' => $organization->resources()->where('resources.is_active', true)->where('resources.type', 'person')->count(),
            'calendar_connections' => DB::table('calendar_connections')
                ->where('organization_id', $organization->getKey())
                ->where('status', CalendarConnectionStatus::Active->value)
                ->count(),
            'storage_mb' => (int) ceil($this->storage->usageBytes($organization) / 1048576),
            'monthly_distance_lookups' => $month->distance_lookup_count,
            'questions' => $this->questionCount($organization),
        ];
    }

    public function assertCanInviteMember(Organization $organization): void
    {
        $pending = $organization->memberInvitations()
            ->whereNull('accepted_at_utc')->whereNull('revoked_at_utc')
            ->where('expires_at_utc', '>', now('UTC'))->count();
        $current = $organization->memberships()->where('status', MembershipStatus::Active->value)->count() + $pending;
        $this->assertAdditional($organization, 'members', $current, 1, 'members');
    }

    public function assertCanAcceptMember(Organization $organization): void
    {
        $current = $organization->memberships()->where('status', MembershipStatus::Active->value)->count();
        $this->assertAdditional($organization, 'members', $current, 1, 'members');
    }

    public function assertCanActivateAppointmentType(Organization $organization, int $additional = 1): void
    {
        $current = $organization->appointmentTypes()->where('is_active', true)->count();
        $this->assertAdditional($organization, 'active_appointment_types', $current, $additional, 'active appointment types');
    }

    public function assertCanActivateResource(Organization $organization, bool $person, int $additional = 1): void
    {
        $resources = $organization->resources()->where('resources.is_active', true)->count();
        $this->assertAdditional($organization, 'resources', $resources, $additional, 'active resources');
        if ($person) {
            $people = $organization->resources()->where('resources.is_active', true)->where('resources.type', 'person')->count();
            $this->assertAdditional($organization, 'person_resources', $people, $additional, 'active person resources');
        }
    }

    public function assertCanActivatePersonResource(Organization $organization, int $additional = 1): void
    {
        $people = $organization->resources()
            ->where('resources.is_active', true)
            ->where('resources.type', 'person')
            ->count();
        $this->assertAdditional($organization, 'person_resources', $people, $additional, 'active person resources');
    }

    public function assertCanAddQuestion(Organization $organization, int $additional = 1): void
    {
        $this->assertAdditional($organization, 'questions', $this->questionCount($organization), $additional, 'questionnaire questions');
    }

    public function assertCanConnectCalendar(Organization $organization): void
    {
        $current = DB::table('calendar_connections')
            ->where('organization_id', $organization->getKey())
            ->where('status', CalendarConnectionStatus::Active->value)
            ->count();
        $this->assertAdditional($organization, 'calendar_connections', $current, 1, 'calendar connections');
    }

    public function assertAddonReductionAllowed(Organization $organization, array $quantities): void
    {
        $usage = $this->usage($organization);
        foreach (PlanAddon::cases() as $addon) {
            $key = $addon->limitKey();
            $limit = $this->limit($organization, $key, $quantities);
            if ($limit !== null && ($usage[$key] ?? 0) > $limit) {
                throw new PlanLimitException(
                    "Reduce {$addon->label()} usage to {$limit} before removing this add-on capacity. Existing data will not be deleted automatically.",
                );
            }
        }

        $personLimit = $this->limit($organization, 'person_resources', $quantities);
        if ($personLimit !== null && ($usage['person_resources'] ?? 0) > $personLimit) {
            throw new PlanLimitException(
                "Reduce active person-resource usage to {$personLimit} before removing member add-on capacity. Existing data will not be deleted automatically.",
            );
        }
    }

    public function assertCanCreateFreeOrganization(User $user): void
    {
        if ($this->platformOwner->isOwner($user)) {
            return;
        }
        $limit = (int) config('plans.limits.free.owned_organizations', 1);
        $owned = $user->person->organizations()
            ->wherePivot('status', MembershipStatus::Active->value)
            ->wherePivot('role', 'owner')
            ->count();
        if ($owned >= $limit) {
            $label = $limit === 1 ? 'owned organization' : 'owned organizations';
            throw new PlanLimitException("The Free plan allows {$limit} {$label}. Contact the platform owner before creating another organization.");
        }
    }

    private function assertAdditional(
        Organization $organization,
        string $key,
        int $current,
        int $additional,
        string $label,
    ): void {
        $limit = $this->limit($organization, $key);
        if ($limit !== null && $current + $additional > $limit) {
            throw new PlanLimitException("This plan allows {$limit} {$label}. Upgrade or add capacity before continuing.");
        }
    }

    private function addonQuantity(Organization $organization, PlanAddon $addon, array $overrides): int
    {
        if (array_key_exists($addon->value, $overrides)) {
            return max(0, (int) $overrides[$addon->value]);
        }

        $record = $organization->planAddons()->where('addon', $addon->value)->first();

        return $record?->effectiveQuantity() ?? 0;
    }

    private function questionCount(Organization $organization): int
    {
        return AppointmentQuestion::query()
            ->whereHas('appointmentType', fn ($query) => $query->where('organization_id', $organization->getKey()))
            ->where(fn ($query) => $query->where('is_active', true)->orWhereDoesntHave('answers'))
            ->count();
    }
}
