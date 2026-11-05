<?php

namespace App\Policies;

use App\Models\AppointmentType;
use App\Models\User;
use App\Support\Organizations\ActiveMemberships;

class AppointmentTypePolicy
{
    public function __construct(private readonly ActiveMemberships $memberships) {}

    public function manage(User $user, AppointmentType $appointmentType): bool
    {
        return $this->memberships->role($user, $appointmentType->organization)?->canManageScheduling() ?? false;
    }
}
