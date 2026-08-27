<?php

namespace App\Policies;

use App\Enums\MembershipRole;
use App\Enums\MembershipStatus;
use App\Models\Resource;
use App\Models\User;

class ResourcePolicy
{
    public function manage(User $user, Resource $resource): bool
    {
        return $resource->organizations()->whereHas('memberships', fn ($query) => $query
            ->where('person_id', $user->person_id)
            ->where('status', MembershipStatus::Active->value)
            ->whereIn('role', [
                MembershipRole::Owner->value,
                MembershipRole::Administrator->value,
                MembershipRole::Manager->value,
            ]))->exists();
    }
    public function calendar(User $user, Resource $resource): bool
    {
        if ($resource->person_id !== null && hash_equals($resource->person_id, $user->person_id)) {
            return true;
        }

        return $this->manage($user, $resource);
    }

}
