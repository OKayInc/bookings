<?php

namespace App\Domain\Availability;

use Carbon\CarbonImmutable;

final readonly class AvailabilitySlot
{
    public function __construct(
        public CarbonImmutable $startsAtUtc,
        public CarbonImmutable $endsAtUtc,
        public array $equipmentAvailability = [],
    ) {
    }

    public function toArray(): array
    {
        return [
            'starts_at_utc' => $this->startsAtUtc->toIso8601String(),
            'ends_at_utc' => $this->endsAtUtc->toIso8601String(),
            'equipment_availability' => $this->equipmentAvailability,
        ];
    }
}
