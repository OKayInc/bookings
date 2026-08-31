<?php

namespace App\Enums;

use InvalidArgumentException;

enum NumericComparisonOperator: string
{
    case GreaterThan = '>';
    case GreaterThanOrEqual = '>=';
    case Equal = '=';
    case LessThanOrEqual = '<=';
    case LessThan = '<';
    case NotEqual = '!=';

    public static function normalize(string $operator): self
    {
        $operator = trim($operator);

        return self::tryFrom(in_array($operator, ['<>', '!'], true) ? '!=' : $operator)
            ?? throw new InvalidArgumentException('Choose a supported numeric comparison operator.');
    }

    public function label(): string
    {
        return match ($this) {
            self::GreaterThan => '> Greater than',
            self::GreaterThanOrEqual => '>= Greater than or equal to',
            self::Equal => '= Equal to',
            self::LessThanOrEqual => '<= Less than or equal to',
            self::LessThan => '< Less than',
            self::NotEqual => '!= Different from (also <> or !)',
        };
    }
}
