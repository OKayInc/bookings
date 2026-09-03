<?php

namespace Tests\Feature;

use App\Domain\Coupons\CouponIssuanceService;
use App\Domain\Coupons\CouponQrCodeService;
use App\Domain\Coupons\CouponRedemptionService;
use App\Domain\Payments\PaymentStateService;
use App\Domain\Questionnaires\QuestionnaireQuote;
use App\Domain\Questionnaires\QuestionnaireSubmission;
use App\Enums\CouponDeliveryMethod;
use App\Enums\CouponDiscountType;
use App\Enums\CouponStatus;
use App\Enums\MembershipRole;
use App\Enums\MembershipStatus;
use App\Enums\PaymentPurpose;
use App\Enums\PaymentTransactionStatus;
use App\Models\AppointmentQuestion;
use App\Models\AppointmentType;
use App\Models\CouponOffer;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\PaymentTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\TestCase;

class M9R3CouponSupportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
    }

    public function test_question_options_use_numeric_order_then_alphabetic_label(): void
    {
        [$user, $organization, $type] = $this->context();
        $this->actingAs($user)->withSession(['active_organization_uuid' => $organization->uuid])
            ->post(route('appointment-types.questions.store', $type), [
                'type' => 'select', 'label' => 'Package', 'is_active' => '1', 'position' => 1,
                'options' => [
                    ['label' => 'Zulu', 'position' => '1'],
                    ['label' => 'beta', 'position' => ''],
                    ['label' => 'Alpha', 'position' => '0'],
                ],
            ])->assertSessionHasNoErrors();

        $question = AppointmentQuestion::firstOrFail();
        $this->assertSame(['Alpha', 'beta', 'Zulu'], $question->options()->pluck('label')->all());
        $this->assertSame([0, 0, 1], $question->options()->pluck('position')->all());
    }

    public function test_manual_fixed_coupon_snapshots_selected_appointments_and_applies_partial_balance(): void
    {
        [$user, $organization, $type] = $this->context();
        $other = $this->appointmentType($organization, 'Other', 'other');
        $coupon = app(CouponIssuanceService::class)->manual($organization, $user->person, [
            'discount_type' => CouponDiscountType::Fixed,
            'amount_minor' => 5000,
            'percentage_bps' => null,
            'applies_to_all' => false,
            'expires_on' => null,
            'recipient_name' => 'Recipient',
            'recipient_email' => null,
            'message' => null,
            'delivery_method' => CouponDeliveryMethod::Print,
        ], 'protected-pass', [$type->getKey()]);

        $application = app(CouponRedemptionService::class)->apply(
            $coupon->code,
            $type,
            new QuestionnaireSubmission([], new QuestionnaireQuote(10000, 3000, [])),
        );
        $this->assertSame(3000, $application->discountMinor);
        $this->assertSame(0, $application->submission->quote->totalMinor);
        $this->assertSame(2000, $application->balanceAfterMinor);

        $this->expectException(\RuntimeException::class);
        app(CouponRedemptionService::class)->apply(
            $coupon->code,
            $other,
            new QuestionnaireSubmission([], new QuestionnaireQuote(10000, 3000, [])),
        );
    }

    public function test_protected_coupon_page_requires_the_buyer_password_and_qr_is_svg(): void
    {
        [$user, $organization, $type] = $this->context();
        $coupon = app(CouponIssuanceService::class)->manual($organization, $user->person, [
            'discount_type' => CouponDiscountType::Percentage,
            'amount_minor' => null,
            'percentage_bps' => 1500,
            'applies_to_all' => true,
            'expires_on' => null,
            'recipient_name' => null,
            'recipient_email' => null,
            'message' => null,
            'delivery_method' => CouponDeliveryMethod::Print,
        ], 'protected-pass', []);

        $this->get(route('public.coupons.view', $coupon->view_token))->assertOk()->assertDontSee($coupon->code);
        $this->post(route('public.coupons.unlock', $coupon->view_token), ['password' => 'wrong-pass'])->assertSessionHasErrors('password');
        $this->post(route('public.coupons.unlock', $coupon->view_token), ['password' => 'protected-pass'])->assertRedirect();
        $this->get(route('public.coupons.view', $coupon->view_token))->assertOk()->assertSee($coupon->code);
        $this->assertStringContainsString('<svg', app(CouponQrCodeService::class)->svg(route('public.coupons.view', $coupon->view_token)));
    }

    public function test_admin_destruction_of_an_unused_purchased_coupon_refunds_its_payment(): void
    {
        [$user, $organization] = $this->context();
        $organization->paymentSettings()->create([
            'stripe_enabled' => true, 'stripe_test_mode' => true,
            'stripe_secret_key' => 'sk_test_coupon', 'stripe_webhook_secret' => 'whsec_coupon',
        ]);
        $offer = CouponOffer::create([
            'organization_id' => $organization->getKey(), 'name' => 'Gift 50',
            'discount_type' => 'fixed', 'amount_minor' => 5000, 'percentage_bps' => null,
            'purchase_price_minor' => 5000, 'applies_to_all' => true,
            'is_public' => true, 'is_active' => true,
        ]);
        $coupon = app(CouponIssuanceService::class)->fromOffer($offer, [
            'purchaser_name' => 'Buyer', 'purchaser_email' => 'buyer@example.test',
            'recipient_name' => null, 'recipient_email' => null, 'message' => null,
            'delivery_method' => CouponDeliveryMethod::Print,
        ], 'protected-pass');
        $coupon->update(['status' => CouponStatus::Active->value, 'activated_at_utc' => now('UTC')]);
        PaymentTransaction::create([
            'organization_id' => $organization->getKey(), 'coupon_id' => $coupon->getKey(),
            'provider' => 'stripe', 'purpose' => PaymentPurpose::CouponPurchase->value,
            'status' => PaymentTransactionStatus::Succeeded->value, 'amount_minor' => 5000, 'currency' => 'CAD',
            'idempotency_key' => (string) Str::uuid(), 'return_token_hash' => hash('sha256', 'return', true),
            'provider_capture_id' => 'pi_coupon', 'completed_at_utc' => now('UTC'),
        ]);
        Http::fake(['https://api.stripe.com/v1/refunds' => Http::response(['id' => 're_coupon', 'status' => 'succeeded'])]);

        $this->actingAs($user)->withSession(['active_organization_uuid' => $organization->uuid])
            ->delete(route('coupons.destroy', $coupon), ['reason' => 'Administrative correction'])
            ->assertSessionHas('success');

        $this->assertSame(CouponStatus::Destroyed, $coupon->fresh()->status);
        $this->assertNotNull($coupon->fresh()->refunded_at_utc);
        $this->assertSame(5000, (int) $coupon->refunds()->sum('amount_minor'));
        Http::assertSentCount(1);
    }

    public function test_public_offer_purchase_uses_the_organization_payment_account_and_activates_on_exact_capture(): void
    {
        [, $organization] = $this->context();
        $organization->paymentSettings()->create([
            'stripe_enabled' => true, 'stripe_test_mode' => true,
            'stripe_secret_key' => 'sk_test_coupon', 'stripe_webhook_secret' => 'whsec_coupon',
        ]);
        $offer = CouponOffer::create([
            'organization_id' => $organization->getKey(), 'name' => 'Ten percent',
            'discount_type' => 'percentage', 'percentage_bps' => 1000, 'amount_minor' => null,
            'purchase_price_minor' => 2500, 'applies_to_all' => true,
            'is_public' => true, 'is_active' => true,
        ]);
        Http::fake(['https://api.stripe.com/v1/checkout/sessions' => Http::response([
            'id' => 'cs_coupon', 'url' => 'https://checkout.stripe.test/coupon', 'status' => 'open',
        ])]);

        $this->post(route('public.coupons.purchase', [$organization->slug, $offer]), [
            'purchaser_name' => 'Buyer', 'purchaser_email' => 'buyer@example.test',
            'recipient_name' => 'Recipient', 'delivery_method' => 'print',
            'password' => 'protected-pass', 'password_confirmation' => 'protected-pass',
            'provider' => 'stripe',
        ])->assertRedirect('https://checkout.stripe.test/coupon');

        $payment = PaymentTransaction::query()->firstOrFail();
        $this->assertSame(PaymentPurpose::CouponPurchase, $payment->purpose);
        $this->assertNull($payment->booking_id);
        $this->assertNotNull($payment->coupon_id);
        $coupon = app(PaymentStateService::class)->completeCoupon($payment, 'pi_coupon_purchase', 2500, 'cad', ['id' => 'cs_coupon']);
        $this->assertSame(CouponStatus::Active, $coupon->status);
    }

    private function context(): array
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create(['currency' => 'CAD']);
        OrganizationMembership::create([
            'organization_id' => $organization->getKey(), 'person_id' => $user->person_id,
            'role' => MembershipRole::Owner, 'status' => MembershipStatus::Active,
        ]);
        return [$user, $organization, $this->appointmentType($organization, 'Coupon Test', 'coupon-test')];
    }

    private function appointmentType(Organization $organization, string $name, string $slug): AppointmentType
    {
        return AppointmentType::create([
            'organization_id' => $organization->getKey(), 'name' => $name, 'slug' => $slug,
            'visibility' => 'public', 'attendance_mode' => 'single', 'capacity' => 1,
            'duration_mode' => 'fixed', 'duration_unit' => 'minute', 'duration_value' => 60,
            'buffer_before_minutes' => 0, 'buffer_after_minutes' => 0,
            'pricing_mode' => 'fixed', 'fixed_price_minor' => 10000,
            'email_verification_mode' => 'none', 'is_active' => true,
        ]);
    }
}
