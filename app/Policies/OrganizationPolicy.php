<?php

namespace App\Policies;

use App\Enums\MembershipRole;
use App\Models\Organization;
use App\Models\User;
use App\Support\Organizations\ActiveMemberships;

class OrganizationPolicy
{
    public function __construct(private readonly ActiveMemberships $memberships) {}

    public function delete(User $user, Organization $organization): bool
    {
        return $this->memberships->role($user, $organization) === MembershipRole::Owner;
    }

    public function update(User $user, Organization $organization): bool
    {
        return $this->memberships->role($user, $organization)?->canManageOrganization() ?? false;
    }

    public function manageScheduling(User $user, Organization $organization): bool
    {
        return $this->memberships->role($user, $organization)?->canManageScheduling() ?? false;
    }

    public function checkInTickets(User $user, Organization $organization): bool
    {
        return $this->memberships->role($user, $organization) !== null;
    }

}
