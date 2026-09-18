<?php

namespace App\Domain\Questionnaires;

use App\Models\AppointmentQuestion;
use InvalidArgumentException;

class DrivingDistancePricingService
{
    private const METERS_PER_KILOMETER = 1000;

    private const METERS_PER_MILE = 1609.344;

    public function charge(AppointmentQuestion $question, int $meters): ?DrivingDistanceCharge
    {
        $configuration = (array) data_get($question->configuration, 'distance_pricing', []);
        if (! ($configuration['enabled'] ?? false) || $meters < 0) {
            return null;
        }

        $unit = in_array($configuration['unit'] ?? null, ['kilometer', 'mile'], true)
            ? $configuration['unit']
            : 'kilometer';
        $measurement = $this->measurement($meters, $unit);
        $mode = $configuration['mode'] ?? 'fixed';

        if ($mode === 'fixed') {
            return new DrivingDistanceCharge(
                (int) ($configuration['fixed_amount_minor'] ?? 0),
                'distance_fixed',
                $measurement['label'],
                [
                    'distance_meters' => $meters,
                    'distance_value' => $measurement['value'],
                    'distance_unit' => $unit,
                    'pricing_mode' => 'fixed',
                ],
            );
        }

        if ($mode !== 'range') {
            return null;
        }

        foreach ((array) ($configuration['ranges'] ?? []) as $range) {
            $minimum = (float) ($range['minimum'] ?? 0);
            $maximum = array_key_exists('maximum', $range) && $range['maximum'] !== null
                ? (float) $range['maximum']
                : null;
            $minimumMeters = $this->thresholdMeters($minimum, $unit);
            $maximumMeters = $maximum === null ? null : $this->thresholdMeters($maximum, $unit);

            if ($meters < $minimumMeters || ($maximumMeters !== null && $meters >= $maximumMeters)) {
                continue;
            }

            return new DrivingDistanceCharge(
                (int) ($range['amount_minor'] ?? 0),
                'distance_range',
                $measurement['label'],
                [
                    'distance_meters' => $meters,
                    'distance_value' => $measurement['value'],
                    'distance_unit' => $unit,
                    'pricing_mode' => 'range',
                    'range_minimum' => $minimum,
                    'range_maximum' => $maximum,
                ],
            );
        }

        $fallback = (array) ($configuration['fallback'] ?? []);
        $increment = (float) ($fallback['increment'] ?? 0);
        $amountPerIncrement = (int) ($fallback['amount_minor'] ?? 0);
        if (! is_finite($increment) || $increment < 0.001 || $increment > 1000000 || $amountPerIncrement <= 0) {
            throw new InvalidArgumentException(
                'The distance ranges do not cover this route and no valid per-distance fallback is configured for '.$question->label.'.',
            );
        }

        $incrementMeters = max(1, $this->thresholdMeters($increment, $unit));
        $blocks = intdiv($meters, $incrementMeters) + ($meters % $incrementMeters === 0 ? 0 : 1);
        if ($blocks > 0 && $amountPerIncrement > intdiv(PHP_INT_MAX, $blocks)) {
            throw new InvalidArgumentException('The fallback distance fee is too large.');
        }

        return new DrivingDistanceCharge(
            $amountPerIncrement * $blocks,
            'distance_fallback',
            $measurement['label'],
            [
                'distance_meters' => $meters,
                'distance_value' => $measurement['value'],
                'distance_unit' => $unit,
                'pricing_mode' => 'range_fallback',
                'fallback_increment' => $increment,
                'fallback_amount_minor' => $amountPerIncrement,
                'fallback_blocks' => $blocks,
            ],
        );
    }

    public function measurement(int $meters, string $unit): array
    {
        $unit = $unit === 'mile' ? 'mile' : 'kilometer';
        $value = round($meters / ($unit === 'mile' ? self::METERS_PER_MILE : self::METERS_PER_KILOMETER), 2);
        $formatted = rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');

        return [
            'meters' => $meters,
            'value' => $value,
            'unit' => $unit,
            'label' => $formatted.' '.($unit === 'mile' ? 'mi' : 'km'),
        ];
    }

    private function thresholdMeters(float $value, string $unit): int
    {
        return (int) round($value * ($unit === 'mile' ? self::METERS_PER_MILE : self::METERS_PER_KILOMETER));
    }
}
