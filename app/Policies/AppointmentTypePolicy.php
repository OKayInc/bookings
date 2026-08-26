<?php

namespace App\Policies;

use App\Enums\MembershipRole;
use App\Enums\MembershipStatus;
use App\Models\AppointmentType;
use App\Models\User;

class AppointmentTypePolicy
{
    public function manage(User $user, AppointmentType $appointmentType): bool
    {
        return $appointmentType->organization->memberships()
            ->where('person_id', $user->person_id)
            ->where('status', MembershipStatus::Active->value)
            ->whereIn('role', [
                MembershipRole::Owner->value,
                MembershipRole::Administrator->value,
                MembershipRole::Manager->value,
            ])->exists();
    }
}
