<?php

namespace App\Domain\Galleries;

use App\Domain\Plans\PlanEntitlementService;
use App\Enums\PlanLevel;
use App\Models\AppointmentType;
use App\Models\Organization;

class GalleryLimitService
{
    public function __construct(private readonly PlanEntitlementService $entitlements)
    {
    }

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
        $level = $this->entitlements->for($organization)->level;
        if ($level === PlanLevel::Complimentary) {
            return PHP_INT_MAX;
        }
        $tier = $level === PlanLevel::Business ? 'paid' : 'free';

        return max(0, (int) config("gallery.limits.{$ownerType}.{$tier}", 0));
    }
}
