<?php

namespace App\Domain\Questionnaires;

use App\Models\AppointmentQuestion;

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

        return null;
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
