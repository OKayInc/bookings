<?php

namespace App\Domain\Coupons;

use App\Domain\Questionnaires\QuestionnaireSubmission;
use App\Models\Coupon;

readonly class CouponApplication
{
    public function __construct(
        public Coupon $coupon,
        public QuestionnaireSubmission $submission,
        public int $discountMinor,
        public ?int $balanceBeforeMinor,
        public ?int $balanceAfterMinor,
    ) {
    }
}
