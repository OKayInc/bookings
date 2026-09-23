<?php

namespace App\Domain\Plans;

use App\Models\Organization;
use App\Models\PlanAuditEvent;
use App\Models\User;

class PlanAuditService
{
    public function record(
        string $event,
        ?Organization $organization = null,
        ?User $actor = null,
        array $details = [],
    ): PlanAuditEvent {
        return PlanAuditEvent::create([
            'organization_id' => $organization?->getKey(),
            'actor_user_id' => $actor?->getKey(),
            'event' => $event,
            'details' => $details === [] ? null : $details,
            'created_at' => now('UTC'),
        ]);
    }
}
