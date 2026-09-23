<?php

namespace App\Domain\Plans;

use App\Enums\PlanLevel;
use Carbon\CarbonImmutable;

final readonly class PlanEntitlement
{
    public function __construct(
        public PlanLevel $level,
        public string $source,
        public ?CarbonImmutable $expiresAt = null,
    ) {}

    public function hasBusinessFeatures(): bool
    {
        return $this->level->hasBusinessFeatures();
    }

    public function isUnlimited(): bool
    {
        return $this->level->isUnlimited();
    }
}
