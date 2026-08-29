<?php

namespace App\Domain\Questionnaires;

readonly class DrivingDistanceCharge
{
    public function __construct(
        public int $amountMinor,
        public string $lineType,
        public string $distanceLabel,
        public array $metadata,
    ) {
    }
}
