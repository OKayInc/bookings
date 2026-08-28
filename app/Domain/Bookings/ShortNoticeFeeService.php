<?php

namespace App\Domain\Bookings;

use App\Enums\PricingAdjustmentType;
use App\Models\AppointmentType;
use App\Models\ShortNoticeFeeRule;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

class ShortNoticeFeeService
{
    public function __construct(private readonly BookingNoticeService $notices)
    {
    }

    public function charge(
        AppointmentType $type,
        CarbonImmutable $startsAtUtc,
        int $subtotalMinor,
        ?CarbonImmutable $nowUtc = null,
    ): ?ShortNoticeFeeCharge {
        $type->loadMissing(['organization', 'shortNoticeFeeRules']);
        $nowUtc ??= CarbonImmutable::now('UTC');
        $timezone = $type->organization->timezone;

        $matched = $type->shortNoticeFeeRules
            ->where('is_active', true)
            ->map(fn (ShortNoticeFeeRule $rule): array => [
                'rule' => $rule,
                'deadline' => $this->notices->deadlineUtc(
                    $nowUtc,
                    $timezone,
                    (int) $rule->threshold_value,
                    $rule->threshold_unit,
                ),
            ])
            ->filter(fn (array $candidate): bool => $startsAtUtc->lte($candidate['deadline']))
            ->sort(function (array $left, array $right): int {
                $deadline = $left['deadline']->getTimestamp() <=> $right['deadline']->getTimestamp();

                return $deadline !== 0
                    ? $deadline
                    : ((int) $left['rule']->position <=> (int) $right['rule']->position);
            })
            ->first();

        if ($matched === null) {
            return null;
        }

        /** @var ShortNoticeFeeRule $rule */
        $rule = $matched['rule'];
        $amountMinor = match ($rule->adjustment_type) {
            PricingAdjustmentType::Fixed => (int) $rule->fixed_amount_minor,
            PricingAdjustmentType::Percentage => $this->percentageAmount(
                $subtotalMinor,
                (int) $rule->percentage_bps,
            ),
            PricingAdjustmentType::None => 0,
        };

        if ($amountMinor <= 0) {
            return null;
        }

        $value = (int) $rule->threshold_value;
        $unit = $rule->threshold_unit;

        return new ShortNoticeFeeCharge(
            ruleUuid: $rule->uuid,
            label: 'Short-notice fee (within '.$value.' '.$unit->plural($value).')',
            lineType: $rule->adjustment_type->value,
            amountMinor: $amountMinor,
            metadata: [
                'threshold_value' => $value,
                'threshold_unit' => $unit->value,
                'percentage_bps' => $rule->percentage_bps,
                'deadline_utc' => $matched['deadline']->toIso8601String(),
            ],
        );
    }

    private function percentageAmount(int $subtotalMinor, int $basisPoints): int
    {
        if ($subtotalMinor <= 0 || $basisPoints <= 0) {
            return 0;
        }

        if ($subtotalMinor > intdiv(PHP_INT_MAX - 5000, $basisPoints)) {
            throw new InvalidArgumentException('Short-notice fee is too large.');
        }

        return intdiv(($subtotalMinor * $basisPoints) + 5000, 10000);
    }
}
