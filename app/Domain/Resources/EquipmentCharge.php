<?php

namespace App\Domain\Resources;

use App\Enums\EquipmentPricingMode;

final readonly class EquipmentCharge
{
    public function __construct(
        public string $resourceUuid,
        public string $resourceName,
        public int $quantity,
        public EquipmentPricingMode $mode,
        public int $amountMinor,
        public array $metadata = [],
    ) {
    }
}
