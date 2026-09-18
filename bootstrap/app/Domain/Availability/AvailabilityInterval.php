<?php

namespace App\Domain\Availability;

use Carbon\CarbonImmutable;

final readonly class AvailabilityInterval
{
    public function __construct(
        public CarbonImmutable $start,
        public CarbonImmutable $end,
    ) {
    }

    public function overlaps(self $other): bool
    {
        return $this->start->lt($other->end) && $this->end->gt($other->start);
    }
}
