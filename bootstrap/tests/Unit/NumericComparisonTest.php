<?php

namespace Tests\Unit;

use App\Domain\Questionnaires\NumericComparison;
use App\Enums\NumericComparisonOperator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class NumericComparisonTest extends TestCase
{
    public static function comparisons(): array
    {
        return json_decode(file_get_contents(__DIR__.'/../Fixtures/numeric-comparisons.json'), true, 512, JSON_THROW_ON_ERROR);
    }

    #[DataProvider('comparisons')]
    public function test_decimal_comparison_and_all_operators(mixed $left, mixed $right, ?int $expected): void
    {
        $this->assertSame($expected, NumericComparison::compare($left, $right));
        $this->assertSame($expected === null ? null : -$expected, NumericComparison::compare($right, $left));
        foreach (['>', '>=', '=', '<=', '<', '<>', '!=', '!'] as $operator) {
            $matches = $expected !== null && match ($operator) {
                '>' => $expected > 0,
                '>=' => $expected >= 0,
                '=' => $expected === 0,
                '<=' => $expected <= 0,
                '<' => $expected < 0,
                default => $expected !== 0,
            };
            $this->assertSame($matches, NumericComparison::matches($left, $operator, $right), $operator);
        }
    }

    public function test_different_aliases_are_normalized_and_unbounded_input_is_rejected(): void
    {
        foreach (['!=', '<>', '!'] as $operator) {
            $this->assertSame(NumericComparisonOperator::NotEqual, NumericComparisonOperator::normalize($operator));
        }
        $this->assertNull(NumericComparison::compare(str_repeat('9', 256), '1'));
        $this->expectException(\InvalidArgumentException::class);
        NumericComparisonOperator::normalize('==');
    }
}
