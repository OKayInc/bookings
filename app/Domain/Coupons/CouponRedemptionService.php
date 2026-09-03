<?php

namespace App\Domain\Coupons;

use App\Domain\Questionnaires\QuestionnairePriceLine;
use App\Domain\Questionnaires\QuestionnaireQuote;
use App\Domain\Questionnaires\QuestionnaireSubmission;
use App\Enums\CouponDiscountType;
use App\Enums\CouponStatus;
use App\Models\AppointmentType;
use App\Models\Booking;
use App\Models\Coupon;
use RuntimeException;

class CouponRedemptionService
{
    public function apply(string $code, AppointmentType $type, QuestionnaireSubmission $submission, bool $lock = false): CouponApplication
    {
        $normalized = Coupon::normalizeCode($code);
        if ($normalized === '') {
            throw new RuntimeException('Enter a gift card or coupon code.');
        }

        $query = Coupon::query()
            ->where('organization_id', $type->organization_id)
            ->where('code_hash', hash('sha256', $normalized, true));
        if ($lock) {
            $query->lockForUpdate();
        }
        $coupon = $query->first();
        if ($coupon === null || $coupon->status !== CouponStatus::Active) {
            throw new RuntimeException('This gift card or coupon is not available.');
        }
        $coupon->loadMissing(['organization', 'appointmentTypes']);
        if ($coupon->isExpired()) {
            throw new RuntimeException('This gift card or coupon has expired.');
        }
        if (! $coupon->applies_to_all && ! $coupon->appointmentTypes->contains(fn ($candidate): bool => $candidate->is($type))) {
            throw new RuntimeException('This gift card or coupon is not valid for the selected appointment.');
        }

        $total = $submission->quote->totalMinor;
        if ($total <= 0) {
            throw new RuntimeException('This booking has no amount to discount.');
        }
        $before = null;
        $after = null;
        if ($coupon->discount_type === CouponDiscountType::Fixed) {
            $before = max(0, (int) $coupon->remaining_amount_minor);
            $discount = min($total, $before);
            $after = $before - $discount;
        } else {
            $discount = $this->percentage($total, (int) $coupon->percentage_bps);
            $discount = min($total, $discount);
        }
        if ($discount <= 0) {
            throw new RuntimeException('This gift card or coupon has no remaining value.');
        }

        $lines = [...$submission->quote->lines, new QuestionnairePriceLine(
            'coupon',
            $coupon->uuid,
            'Gift card / coupon '.strtoupper((string) $coupon->code),
            'coupon_discount',
            '1',
            $discount,
            ['discount_type' => $coupon->discount_type->value],
        )];
        $quote = new QuestionnaireQuote($submission->quote->basePriceMinor, $total - $discount, $lines);

        return new CouponApplication($coupon, new QuestionnaireSubmission($submission->answers, $quote), $discount, $before, $after);
    }

    public function record(CouponApplication $application, Booking $booking): void
    {
        $application->coupon->redemptions()->create([
            'organization_id' => $booking->organization_id,
            'booking_id' => $booking->getKey(),
            'discount_minor' => $application->discountMinor,
            'balance_before_minor' => $application->balanceBeforeMinor,
            'balance_after_minor' => $application->balanceAfterMinor,
            'redeemed_at_utc' => now('UTC'),
        ]);

        $application->coupon->update([
            'remaining_amount_minor' => $application->balanceAfterMinor,
            'status' => $application->coupon->discount_type === CouponDiscountType::Percentage
                || $application->balanceAfterMinor === 0
                ? CouponStatus::Used->value
                : CouponStatus::Active->value,
        ]);
    }

    private function percentage(int $amountMinor, int $basisPoints): int
    {
        if ($amountMinor > intdiv(PHP_INT_MAX - 5000, max(1, $basisPoints))) {
            throw new RuntimeException('The coupon discount is too large.');
        }

        return intdiv(($amountMinor * $basisPoints) + 5000, 10000);
    }
}
