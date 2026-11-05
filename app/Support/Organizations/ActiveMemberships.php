<?php

namespace App\Support\Organizations;

use App\Enums\MembershipRole;
use App\Enums\MembershipStatus;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;
use App\Support\PageReadCache;
use Illuminate\Database\Eloquent\Collection;

class ActiveMemberships
{
    public function __construct(private readonly PageReadCache $reads) {}

    /** @return Collection<int, OrganizationMembership> */
    public function forUser(User $user): Collection
    {
        return $this->reads->remember('memberships:'.bin2hex((string) $user->person_id),
            fn (): Collection => OrganizationMembership::query()
                ->where('person_id', $user->person_id)
                ->where('status', MembershipStatus::Active->value)
                ->with('organization')
                ->get());
    }

    public function role(User $user, Organization $organization): ?MembershipRole
    {
        if (! $this->reads->enabled()) {
            return OrganizationMembership::query()
                ->where('organization_id', $organization->getKey())
                ->where('person_id', $user->person_id)
                ->where('status', MembershipStatus::Active->value)
                ->first(['role'])?->role;
        }

        return $this->forUser($user)->first(fn (OrganizationMembership $membership) =>
            hash_equals((string) $membership->organization_id, (string) $organization->getKey()))?->role;
    }
}
