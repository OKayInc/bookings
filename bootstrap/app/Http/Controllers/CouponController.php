<?php

namespace App\Http\Controllers;

use App\Domain\Coupons\CouponIssuanceService;
use App\Domain\Money\MoneyService;
use App\Domain\Payments\PaymentRefundService;
use App\Domain\Questionnaires\PercentageService;
use App\Enums\CouponDeliveryMethod;
use App\Enums\CouponDiscountType;
use App\Models\Coupon;
use App\Models\CouponOffer;
use App\Models\PaymentRefund;
use App\Rules\MoneyAmount;
use App\Support\Organizations\OrganizationContext;
use App\Support\Uuid\UuidBinary;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;

class CouponController extends Controller
{
    public function index(OrganizationContext $context): View
    {
        $organization = $context->organization();
        $this->authorize('update', $organization);

        return view('coupons.index', [
            'organization' => $organization,
            'appointmentTypes' => $organization->appointmentTypes()->where('is_active', true)->orderBy('name')->get(),
            'offers' => $organization->couponOffers()->with('appointmentTypes')->latest()->get(),
            'coupons' => $organization->coupons()->with(['offer', 'redemptions.booking', 'payments', 'refunds'])->latest()->get(),
            'discountTypes' => CouponDiscountType::cases(),
            'deliveryMethods' => CouponDeliveryMethod::cases(),
        ]);
    }

    public function storeOffer(
        Request $request,
        OrganizationContext $context,
        MoneyService $money,
        PercentageService $percentages,
    ): RedirectResponse {
        $organization = $context->organization();
        $this->authorize('update', $organization);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:5000'],
            'discount_type' => ['required', Rule::enum(CouponDiscountType::class)],
            'amount' => ['nullable', new MoneyAmount($organization->currency)],
            'percentage' => ['nullable', 'numeric', 'gt:0', 'max:100'],
            'purchase_price' => ['required', new MoneyAmount($organization->currency)],
            'applies_to_all' => ['nullable', 'boolean'],
            'appointment_types' => ['nullable', 'array', 'max:500'],
            'appointment_types.*' => ['uuid', 'distinct'],
            'expires_on' => ['nullable', 'date_format:Y-m-d'],
            'is_public' => ['nullable', 'boolean'],
        ]);
        $type = CouponDiscountType::from($data['discount_type']);
        if ($type === CouponDiscountType::Fixed && blank($data['amount'] ?? null)) {
            return back()->withInput()->withErrors(['amount' => 'Enter the fixed gift-card value.']);
        }
        if ($type === CouponDiscountType::Percentage && blank($data['percentage'] ?? null)) {
            return back()->withInput()->withErrors(['percentage' => 'Enter the coupon percentage.']);
        }
        $ids = $this->appointmentTypeIds($organization->getKey(), $data['appointment_types'] ?? []);
        $appliesToAll = $request->boolean('applies_to_all');
        if (! $appliesToAll && $ids === []) {
            return back()->withInput()->withErrors(['appointment_types' => 'Select at least one appointment type.']);
        }
        $offer = CouponOffer::create([
            'organization_id' => $organization->getKey(),
            'name' => trim($data['name']),
            'description' => $data['description'] ?? null,
            'discount_type' => $type->value,
            'amount_minor' => $type === CouponDiscountType::Fixed ? $money->parse($data['amount'], $organization->currency) : null,
            'percentage_bps' => $type === CouponDiscountType::Percentage ? $percentages->parseToBasisPoints($data['percentage']) : null,
            'purchase_price_minor' => $money->parse($data['purchase_price'], $organization->currency),
            'applies_to_all' => $appliesToAll,
            'expires_on' => $data['expires_on'] ?? null,
            'is_public' => $request->boolean('is_public'),
            'is_active' => true,
        ]);
        $offer->appointmentTypes()->sync($ids);

        return back()->with('success', 'Purchasable gift card / coupon added.');
    }

    public function toggleOffer(Request $request, CouponOffer $couponOffer, OrganizationContext $context): RedirectResponse
    {
        $organization = $context->organization();
        abort_unless(hash_equals($couponOffer->organization_id, $organization->getKey()), 404);
        $this->authorize('update', $organization);
        if ($request->input('field') === 'visibility') {
            $couponOffer->update(['is_public' => ! $couponOffer->is_public]);
            return back()->with('success', $couponOffer->is_public ? 'Offer published.' : 'Offer hidden from the public page.');
        }
        $couponOffer->update(['is_active' => ! $couponOffer->is_active]);

        return back()->with('success', $couponOffer->is_active ? 'Offer enabled.' : 'Offer disabled.');
    }

    public function storeManual(
        Request $request,
        OrganizationContext $context,
        MoneyService $money,
        PercentageService $percentages,
        CouponIssuanceService $issuance,
    ): RedirectResponse {
        $organization = $context->organization();
        $this->authorize('update', $organization);
        $data = $request->validate([
            'discount_type' => ['required', Rule::enum(CouponDiscountType::class)],
            'amount' => ['nullable', new MoneyAmount($organization->currency)],
            'percentage' => ['nullable', 'numeric', 'gt:0', 'max:100'],
            'applies_to_all' => ['nullable', 'boolean'],
            'appointment_types' => ['nullable', 'array', 'max:500'],
            'appointment_types.*' => ['uuid', 'distinct'],
            'expires_on' => ['nullable', 'date_format:Y-m-d'],
            'recipient_name' => ['nullable', 'string', 'max:240'],
            'recipient_email' => ['nullable', 'email:rfc', 'max:254'],
            'message' => ['nullable', 'string', 'max:5000'],
            'delivery_method' => ['required', Rule::enum(CouponDeliveryMethod::class)],
            'password' => ['required', 'string', 'min:8', 'max:200', 'confirmed'],
        ]);
        $type = CouponDiscountType::from($data['discount_type']);
        if ($type === CouponDiscountType::Fixed && blank($data['amount'] ?? null)) {
            return back()->withInput()->withErrors(['amount' => 'Enter the fixed gift-card value.']);
        }
        if ($type === CouponDiscountType::Percentage && blank($data['percentage'] ?? null)) {
            return back()->withInput()->withErrors(['percentage' => 'Enter the coupon percentage.']);
        }
        $delivery = CouponDeliveryMethod::from($data['delivery_method']);
        if ($delivery !== CouponDeliveryMethod::Print && blank($data['recipient_email'] ?? null)) {
            return back()->withInput()->withErrors(['recipient_email' => 'Enter a recipient email for email delivery.']);
        }
        $ids = $this->appointmentTypeIds($organization->getKey(), $data['appointment_types'] ?? []);
        $appliesToAll = $request->boolean('applies_to_all');
        if (! $appliesToAll && $ids === []) {
            return back()->withInput()->withErrors(['appointment_types' => 'Select at least one appointment type.']);
        }
        $coupon = $issuance->manual($organization, $request->user()->person, [
            'discount_type' => $type,
            'amount_minor' => $type === CouponDiscountType::Fixed ? $money->parse($data['amount'], $organization->currency) : null,
            'percentage_bps' => $type === CouponDiscountType::Percentage ? $percentages->parseToBasisPoints($data['percentage']) : null,
            'applies_to_all' => $appliesToAll,
            'expires_on' => $data['expires_on'] ?? null,
            'recipient_name' => $data['recipient_name'] ?? null,
            'recipient_email' => $data['recipient_email'] ?? null,
            'message' => $data['message'] ?? null,
            'delivery_method' => $delivery,
        ], $data['password'], $ids);
        $issuance->deliver($coupon);

        return $delivery === CouponDeliveryMethod::Print
            ? redirect()->route('coupons.show', $coupon)
            : back()->with('success', 'Gift card / coupon created and sent.');
    }

    public function show(Coupon $coupon, OrganizationContext $context): View
    {
        $organization = $context->organization();
        abort_unless(hash_equals($coupon->organization_id, $organization->getKey()), 404);
        $this->authorize('update', $organization);

        return view('coupons.show', ['organization' => $organization, 'coupon' => $coupon->load('appointmentTypes')]);
    }

    public function destroy(
        Request $request,
        Coupon $coupon,
        OrganizationContext $context,
        PaymentRefundService $refunds,
    ): RedirectResponse {
        $organization = $context->organization();
        abort_unless(hash_equals($coupon->organization_id, $organization->getKey()), 404);
        $this->authorize('update', $organization);
        $data = $request->validate(['reason' => ['required', 'string', 'max:5000']]);

        try {
            $created = $refunds->destroyCoupon($coupon, $data['reason'], $request->user()->person);
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        $incomplete = $created->contains(fn ($refund): bool => $refund->status->value !== 'succeeded');
        return back()->with(
            $incomplete ? 'error' : 'success',
            match (true) {
                $created->isEmpty() => 'Unused coupon destroyed.',
                $incomplete => 'Unused coupon destroyed. Its purchase refund is recorded but incomplete; retry it from this page.',
                default => 'Unused coupon destroyed and its purchase payment was refunded.',
            },
        );
    }

    public function retryRefund(Coupon $coupon, PaymentRefund $refund, OrganizationContext $context, PaymentRefundService $refunds): RedirectResponse
    {
        $organization = $context->organization();
        abort_unless(hash_equals($coupon->organization_id, $organization->getKey()) && hash_equals((string) $refund->coupon_id, $coupon->getKey()), 404);
        $this->authorize('update', $organization);
        $result = $refunds->send($refund->load(['organization.paymentSettings', 'coupon', 'transaction']));

        return back()->with($result->status->value === 'succeeded' ? 'success' : 'error', $result->status->value === 'succeeded' ? 'Refund completed.' : 'Refund is still pending; retry is safe.');
    }

    /** @param list<string> $uuids @return list<string> */
    private function appointmentTypeIds(string $organizationId, array $uuids): array
    {
        $ids = [];
        foreach ($uuids as $uuid) {
            $id = DB::table('appointment_types')->where('organization_id', $organizationId)->where('id', UuidBinary::toBytes($uuid))->value('id');
            if ($id === null) {
                abort(422, 'An appointment type does not belong to this organization.');
            }
            $ids[] = $id;
        }
        return $ids;
    }
}
