<?php

namespace App\Domain\Taxes;

use App\Enums\TaxPriceMode;

readonly class TaxQuote
{
    /** @param list<TaxLine> $lines */
    public function __construct(
        public int $subtotalMinor,
        public int $taxTotalMinor,
        public int $totalMinor,
        public ?TaxPriceMode $priceMode,
        public ?string $taxIdentifier,
        public array $lines,
    ) {}

    public function collectsTaxes(): bool
    {
        return $this->priceMode !== null && $this->lines !== [];
    }
}
