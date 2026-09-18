<?php

namespace App\Domain\Availability;

use App\Models\Appointment;
use Carbon\CarbonImmutable;

final readonly class BookableSlot
{
    public function __construct(
        public CarbonImmutable $startsAtUtc,
        public CarbonImmutable $endsAtUtc,
        public ?Appointment $appointment,
        public int $remainingCapacity,
        public array $equipmentAvailability = [],
    ) {
    }
}
