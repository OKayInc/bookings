<?php

namespace App\Domain\Taxes;

use InvalidArgumentException;

class TaxRate
{
    public const ONE_HUNDRED_PERCENT = 1_000_000;

    public static function fromPercentage(string|int|float $percentage): int
    {
        $value = trim((string) $percentage);
        if (! preg_match('/^(\d{1,3})(?:\.(\d{1,4}))?$/', $value, $matches)) {
            throw new InvalidArgumentException('Enter a tax percentage with up to four decimal places.');
        }

        $whole = (int) $matches[1];
        $fraction = str_pad($matches[2] ?? '', 4, '0');
        $millionths = ($whole * 10_000) + (int) $fraction;

        if ($millionths < 1 || $millionths > self::ONE_HUNDRED_PERCENT) {
            throw new InvalidArgumentException('A tax percentage must be greater than 0 and no more than 100.');
        }

        return $millionths;
    }

    public static function percentage(int $rateMillionths): string
    {
        $value = number_format($rateMillionths / 10_000, 4, '.', '');

        return rtrim(rtrim($value, '0'), '.');
    }
}
