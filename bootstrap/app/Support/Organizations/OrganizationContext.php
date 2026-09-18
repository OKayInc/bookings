<?php

namespace App\Support\Organizations;

use App\Models\Organization;
use RuntimeException;

class OrganizationContext
{
    private ?Organization $organization = null;

    public function set(Organization $organization): void
    {
        $this->organization = $organization;
    }

    public function get(): ?Organization
    {
        return $this->organization;
    }

    public function organization(): Organization
    {
        return $this->organization
            ?? throw new RuntimeException('No active organization is available in this request.');
    }
}
