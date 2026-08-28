<?php

namespace App\Domain\Bookings;

use App\Domain\Money\MoneyService;
use App\Domain\Questionnaires\PercentageService;
use App\Enums\PricingAdjustmentType;
use App\Models\AppointmentType;
use Illuminate\Support\Facades\DB;

class ShortNoticeFeeRuleService
{
    public function __construct(
        private readonly MoneyService $money,
        private readonly PercentageService $percentages,
    ) {
    }

    /** @param list<array<string, mixed>> $rules */
    public function sync(AppointmentType $type, array $rules, string $currency): void
    {
        DB::transaction(function () use ($type, $rules, $currency): void {
            $type->shortNoticeFeeRules()->delete();

            foreach (array_values($rules) as $position => $input) {
                $adjustmentType = PricingAdjustmentType::from((string) $input['adjustment_type']);

                $type->shortNoticeFeeRules()->create([
                    'threshold_value' => (int) $input['threshold_value'],
                    'threshold_unit' => (string) $input['threshold_unit'],
                    'adjustment_type' => $adjustmentType->value,
                    'fixed_amount_minor' => $adjustmentType === PricingAdjustmentType::Fixed
                        ? $this->money->parse((string) $input['fixed_amount'], $currency)
                        : null,
                    'percentage_bps' => $adjustmentType === PricingAdjustmentType::Percentage
                        ? $this->percentages->parseToBasisPoints($input['percentage'] ?? null)
                        : null,
                    'position' => $position + 1,
                    'is_active' => true,
                ]);
            }
        });

        $type->unsetRelation('shortNoticeFeeRules');
    }
}
