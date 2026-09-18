<?php

namespace App\Domain\Taxes;

readonly class TaxLine
{
    public function __construct(
        public string $name,
        public int $rateMillionths,
        public int $amountMinor,
        public int $position,
    ) {}
}
