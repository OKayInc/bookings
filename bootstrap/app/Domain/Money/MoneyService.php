<?php

namespace App\Domain\Money;

use InvalidArgumentException;

class MoneyService
{
    public function __construct(private readonly CurrencyMinorUnits $minorUnits)
    {
    }

    public function parse(string|int $amount, string $currency): int
    {
        $raw = trim((string) $amount);
        $exponent = $this->minorUnits->exponent($currency);

        if ($raw === '' || ! preg_match('/^\d+(?:\.\d+)?$/', $raw)) {
            throw new InvalidArgumentException('The amount must be a non-negative decimal number.');
        }

        [$whole, $fraction] = array_pad(explode('.', $raw, 2), 2, '');

        if (strlen($fraction) > $exponent) {
            throw new InvalidArgumentException(sprintf(
                '%s supports at most %d decimal place%s.',
                strtoupper($currency),
                $exponent,
                $exponent === 1 ? '' : 's',
            ));
        }

        if ($exponent === 0 && $fraction !== '') {
            throw new InvalidArgumentException(sprintf('%s does not use decimal minor units.', strtoupper($currency)));
        }

        $fraction = str_pad($fraction, $exponent, '0');
        $factor = 10 ** $exponent;

        $wholeValue = (int) $whole;
        if ($wholeValue > intdiv(PHP_INT_MAX, max(1, $factor))) {
            throw new InvalidArgumentException('The amount is too large.');
        }

        return ($wholeValue * $factor) + ($fraction === '' ? 0 : (int) $fraction);
    }

    public function decimal(int $minor, string $currency): string
    {
        $exponent = $this->minorUnits->exponent($currency);
        $factor = 10 ** $exponent;
        $whole = intdiv($minor, $factor);
        $fraction = $minor % $factor;

        return $exponent === 0
            ? (string) $whole
            : sprintf('%d.%0'.$exponent.'d', $whole, $fraction);
    }

    public function format(int $minor, string $currency): string
    {
        return strtoupper($currency).' '.$this->decimal($minor, $currency);
    }
}
