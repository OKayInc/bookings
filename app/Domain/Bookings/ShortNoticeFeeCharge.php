<?php

namespace App\Domain\Bookings;

readonly class ShortNoticeFeeCharge
{
    public function __construct(
        public string $ruleUuid,
        public string $label,
        public string $lineType,
        public int $amountMinor,
        public array $metadata,
    ) {
    }
}
