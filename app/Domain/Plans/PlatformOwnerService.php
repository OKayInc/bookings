<?php

namespace App\Domain\Plans;

use App\Models\User;

class PlatformOwnerService
{
    public function isOwner(?User $user): bool
    {
        $configured = strtolower(trim((string) config('plans.platform_owner_user_id')));

        return $user !== null
            && $configured !== ''
            && $user->uuid !== null
            && hash_equals($configured, strtolower($user->uuid));
    }
}
