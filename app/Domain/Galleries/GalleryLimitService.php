<?php

namespace App\Domain\Galleries;

use App\Enums\OrganizationPlanTier;
use App\Models\AppointmentType;
use App\Models\Organization;

class GalleryLimitService
{
    public function forOrganization(Organization $organization): int
    {
        return $this->limit('organization', $organization);
    }

    public function forAppointmentType(AppointmentType $appointmentType): int
    {
        return $this->limit('appointment_type', $appointmentType->organization);
    }

    private function limit(string $ownerType, Organization $organization): int
    {
        $tier = $organization->plan_tier instanceof OrganizationPlanTier
            ? $organization->plan_tier->value
            : OrganizationPlanTier::tryFrom((string) $organization->plan_tier)?->value;

        return max(0, (int) config("gallery.limits.{$ownerType}.".($tier ?? OrganizationPlanTier::Free->value), 0));
    }
}
