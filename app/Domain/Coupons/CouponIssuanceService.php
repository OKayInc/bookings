<?php

namespace App\Domain\Coupons;

use App\Enums\CouponDeliveryMethod;
use App\Enums\CouponDiscountType;
use App\Enums\CouponSource;
use App\Enums\CouponStatus;
use App\Models\Coupon;
use App\Models\CouponOffer;
use App\Models\Organization;
use App\Models\Person;
use App\Notifications\CouponDeliveryEmail;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use RuntimeException;

class CouponIssuanceService
{
    /** @param array<string,mixed> $recipient */
    public function fromOffer(CouponOffer $offer, array $recipient, string $password): Coupon
    {
        $offer->loadMissing(['organization', 'appointmentTypes']);
        if (! $offer->is_active || ! $offer->is_public || ($offer->expires_on && $offer->expires_on->lt(now($offer->organization->timezone)->startOfDay()))) {
            throw new RuntimeException('This gift card or coupon is no longer available.');
        }

        return $this->create($offer->organization, [
            'coupon_offer_id' => $offer->getKey(),
            'source' => CouponSource::Purchased,
            'status' => CouponStatus::Pending,
            'discount_type' => $offer->discount_type,
            'amount_minor' => $offer->amount_minor,
            'percentage_bps' => $offer->percentage_bps,
            'applies_to_all' => $offer->applies_to_all,
            'expires_on' => $offer->expires_on,
            ...$recipient,
        ], $password, $offer->appointmentTypes->modelKeys());
    }

    /** @param array<string,mixed> $data @param list<string> $appointmentTypeIds */
    public function manual(Organization $organization, Person $creator, array $data, string $password, array $appointmentTypeIds): Coupon
    {
        return $this->create($organization, [
            ...$data,
            'created_by_person_id' => $creator->getKey(),
            'source' => CouponSource::Manual,
            'status' => CouponStatus::Active,
            'activated_at_utc' => now('UTC'),
        ], $password, $appointmentTypeIds);
    }

    public function deliver(Coupon $coupon): void
    {
        if ($coupon->delivery_method === CouponDeliveryMethod::Print || $coupon->delivered_at_utc !== null) {
            return;
        }
        if (! filter_var($coupon->recipient_email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('A recipient email is required for email delivery.');
        }
        $claimed = Coupon::query()->whereKey($coupon->getKey())->whereNull('delivered_at_utc')
            ->update(['delivered_at_utc' => now('UTC'), 'updated_at' => now()]);
        if ($claimed === 0) {
            return;
        }
        try {
            Notification::route('mail', $coupon->recipient_email)->notify(new CouponDeliveryEmail($coupon));
        } catch (\Throwable $exception) {
            Coupon::query()->whereKey($coupon->getKey())->update(['delivered_at_utc' => null, 'updated_at' => now()]);
            throw $exception;
        }
    }

    /** @param array<string,mixed> $data @param list<string> $appointmentTypeIds */
    private function create(Organization $organization, array $data, string $password, array $appointmentTypeIds): Coupon
    {
        if (trim($password) === '') {
            throw new RuntimeException('Set a password for the protected coupon page.');
        }
        $raw = strtoupper(Str::random(12));
        $code = implode('-', str_split($raw, 4));
        $token = Str::random(32);
        $discountType = $data['discount_type'] instanceof CouponDiscountType
            ? $data['discount_type']
            : CouponDiscountType::from((string) $data['discount_type']);
        $coupon = Coupon::create([
            ...$data,
            'organization_id' => $organization->getKey(),
            'source' => $data['source'] instanceof CouponSource ? $data['source']->value : $data['source'],
            'status' => $data['status'] instanceof CouponStatus ? $data['status']->value : $data['status'],
            'code' => $code,
            'code_hash' => hash('sha256', Coupon::normalizeCode($code), true),
            'view_token' => $token,
            'view_token_hash' => hash('sha256', $token, true),
            'discount_type' => $discountType->value,
            'remaining_amount_minor' => $discountType === CouponDiscountType::Fixed ? (int) $data['amount_minor'] : null,
            'delivery_method' => $data['delivery_method'] instanceof CouponDeliveryMethod
                ? $data['delivery_method']->value
                : $data['delivery_method'],
            'password_hash' => Hash::make($password),
        ]);
        if (! $coupon->applies_to_all) {
            $coupon->appointmentTypes()->sync($appointmentTypeIds);
        }

        return $coupon->fresh(['organization', 'appointmentTypes']);
    }
}
