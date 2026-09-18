<?php

namespace App\Support\Organizations;

use App\Enums\MembershipStatus;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Http\Request;

class ActiveOrganizationResolver
{
    public function resolve(User $user, Request $request): ?Organization
    {
        $memberships = $user->person
            ->memberships()
            ->with('organization')
            ->where('status', MembershipStatus::Active->value)
            ->get();

        if ($memberships->isEmpty()) {
            return null;
        }

        $preferredKey = $user->getAttribute('active_organization_id');
        $membership = $preferredKey
            ? $memberships->first(fn ($item) => hash_equals((string) $item->organization_id, (string) $preferredKey))
            : null;

        if (! $membership) {
            $requestedUuid = (string) $request->session()->get('active_organization_uuid', '');
            $membership = $requestedUuid !== ''
                ? $memberships->first(fn ($item) => $item->organization && hash_equals($item->organization->uuid, $requestedUuid))
                : null;
        }

        $membership ??= $memberships->first();
        $organization = $membership->organization;

        $this->select($user, $organization, $request, saveUser: $preferredKey === null || ! hash_equals((string) $preferredKey, (string) $organization->getKey()));

        return $organization;
    }

    public function select(User $user, Organization $organization, Request $request, bool $saveUser = true): void
    {
        if ($saveUser) {
            $user->forceFill(['active_organization_id' => $organization->getKey()])->save();
        }

        $request->session()->put('active_organization_uuid', $organization->uuid);
    }
}
