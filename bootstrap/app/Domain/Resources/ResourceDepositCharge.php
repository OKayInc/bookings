<?php

namespace App\Domain\Resources;

readonly class ResourceDepositCharge
{
    public function __construct(
        public ?string $resourceUuid,
        public string $resourceName,
        public int $quantity,
        public int $unitAmountMinor,
        public int $amountMinor,
        public string $configurationSource,
        public ?string $questionUuid = null,
        public ?string $questionLabel = null,
    ) {
    }
}
