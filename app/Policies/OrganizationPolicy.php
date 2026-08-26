<?php

namespace App\Policies;

use App\Enums\MembershipRole;
use App\Enums\MembershipStatus;
use App\Models\Organization;
use App\Models\User;

class OrganizationPolicy
{
    public function update(User $user, Organization $organization): bool
    {
        return $organization->memberships()
            ->where('person_id', $user->person_id)
            ->where('status', MembershipStatus::Active->value)
            ->whereIn('role', [MembershipRole::Owner->value, MembershipRole::Administrator->value])
            ->exists();
    }

    public function manageScheduling(User $user, Organization $organization): bool
    {
        return $organization->memberships()
            ->where('person_id', $user->person_id)
            ->where('status', MembershipStatus::Active->value)
            ->whereIn('role', [
                MembershipRole::Owner->value,
                MembershipRole::Administrator->value,
                MembershipRole::Manager->value,
            ])->exists();
    }

}
