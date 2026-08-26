<?php

namespace App\Domain\Appointments;

use App\Enums\DurationUnit;
use App\Enums\PricingMode;
use App\Models\AppointmentType;
use InvalidArgumentException;

class AppointmentTypePricingService
{
    public function priceForDuration(
        AppointmentType $appointmentType,
        ?int $durationValue = null,
        DurationUnit|string|null $durationUnit = null,
    ): int {
        return match ($appointmentType->pricing_mode) {
            PricingMode::Free => 0,
            PricingMode::Fixed => (int) ($appointmentType->fixed_price_minor ?? 0),
            PricingMode::Rate => $this->ratePrice($appointmentType, $durationValue, $durationUnit),
        };
    }

    private function ratePrice(
        AppointmentType $appointmentType,
        ?int $durationValue,
        DurationUnit|string|null $durationUnit,
    ): int {
        $durationValue ??= $appointmentType->duration_mode->value === 'fixed'
            ? $appointmentType->duration_value
            : $appointmentType->minimum_duration_value;

        $durationUnit ??= $appointmentType->duration_unit;
        $durationUnit = $durationUnit instanceof DurationUnit ? $durationUnit : DurationUnit::from((string) $durationUnit);
        $rateUnit = $appointmentType->rate_unit;

        if ($durationValue === null || $durationValue < 1 || $rateUnit === null || $appointmentType->rate_amount_minor === null) {
            throw new InvalidArgumentException('The appointment type does not contain a complete rate configuration.');
        }

        $durationMinutes = $durationValue * $durationUnit->minutes();
        $denominator = $rateUnit->minutes();
        $rateAmount = (int) $appointmentType->rate_amount_minor;

        if ($durationMinutes > 0 && $rateAmount > intdiv(PHP_INT_MAX, $durationMinutes)) {
            throw new InvalidArgumentException('The calculated appointment price is too large.');
        }

        $numerator = $rateAmount * $durationMinutes;

        // Positive-money half-up rounding without floating-point arithmetic.
        return intdiv($numerator + intdiv($denominator, 2), $denominator);
    }
}
