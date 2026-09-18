<?php

namespace App\Domain\Bookings;

use App\Enums\PricingAdjustmentType;
use App\Enums\PricingMode;
use App\Models\AppointmentType;

class BookingPriceVisibilityService
{
    public function shouldShow(AppointmentType $type, int $totalBeforeDiscount): bool
    {
        if ($type->pricing_mode !== PricingMode::Free || $totalBeforeDiscount > 0) {
            return true;
        }
        $type->loadMissing('questions.options');
        foreach ($type->questions->where('is_active', true) as $question) {
            if ($question->pricing_adjustment_type !== PricingAdjustmentType::None
                || data_get($question->configuration, 'distance_pricing.enabled', false)
                || $question->options->where('is_active', true)->contains(
                    fn ($option) => $option->pricing_adjustment_type !== PricingAdjustmentType::None,
                )) {
                return true;
            }
        }
        return false;
    }
}
