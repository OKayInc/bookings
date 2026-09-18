<?php

namespace App\Domain\Questionnaires;

use App\Enums\NumericComparisonOperator;

/** Compare decimal text without rounding through floating-point numbers. */
class NumericComparison
{
    public static function matches(mixed $left, string $operator, mixed $right): bool
    {
        $comparison = self::compare($left, $right);
        if ($comparison === null) {
            return false; // Missing/invalid numbers never match, including !=.
        }

        return match (NumericComparisonOperator::normalize($operator)) {
            NumericComparisonOperator::GreaterThan => $comparison > 0,
            NumericComparisonOperator::GreaterThanOrEqual => $comparison >= 0,
            NumericComparisonOperator::Equal => $comparison === 0,
            NumericComparisonOperator::LessThanOrEqual => $comparison <= 0,
            NumericComparisonOperator::LessThan => $comparison < 0,
            NumericComparisonOperator::NotEqual => $comparison !== 0,
        };
    }

    public static function compare(mixed $left, mixed $right): ?int
    {
        $a = self::parts($left);
        $b = self::parts($right);
        if ($a === null || $b === null) {
            return null;
        }
        if ($a['sign'] !== $b['sign']) {
            return $a['sign'] <=> $b['sign'];
        }
        if ($a['sign'] === 0) {
            return 0;
        }

        $magnitude = $a['power'] <=> $b['power'];
        if ($magnitude === 0) {
            $length = max(strlen($a['digits']), strlen($b['digits']));
            $magnitude = strcmp(str_pad($a['digits'], $length, '0'), str_pad($b['digits'], $length, '0')) <=> 0;
        }

        return $a['sign'] * $magnitude;
    }

    private static function parts(mixed $value): ?array
    {
        if (! is_string($value) && ! is_int($value) && ! is_float($value)) {
            return null;
        }
        $text = trim((string) $value);
        if (strlen($text) > 255 || ! preg_match('/^([+-]?)(\d+(?:\.\d*)?|\.\d+)(?:[eE]([+-]?\d+))?$/D', $text, $matches)) {
            return null;
        }
        $exponentText = $matches[3] ?? '0';
        if (strlen(ltrim($exponentText, '+-0')) > 4 || abs((int) $exponentText) > 1000) {
            return null;
        }
        [$integer, $fraction] = array_pad(explode('.', $matches[2], 2), 2, '');
        $allDigits = $integer.$fraction;
        $digits = ltrim($allDigits, '0');
        if ($digits === '') {
            return ['sign' => 0, 'digits' => '0', 'power' => 0];
        }

        return [
            'sign' => $matches[1] === '-' ? -1 : 1,
            'digits' => rtrim($digits, '0'),
            'power' => strlen($integer) - (strlen($allDigits) - strlen($digits)) + (int) $exponentText,
        ];
    }
}
