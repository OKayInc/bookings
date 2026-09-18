<?php

namespace Tests\Feature;

use App\Domain\Payments\BookingPaymentSnapshotService;
use App\Domain\Payments\PaymentCheckoutService;
use App\Domain\Payments\PaymentRefundService;
use App\Domain\Payments\PaymentRuleService;
use App\Domain\Payments\PaymentStateService;
use App\Domain\Payments\PaymentWebhookService;
use App\Domain\Payments\StripePaymentGateway;
use App\Enums\PaymentRefundStatus;
use App\Enums\PaymentProvider;
use App\Enums\PaymentPurpose;
use App\Enums\PaymentTransactionStatus;
use App\Models\Appointment;
use App\Models\AppointmentType;
use App\Models\Booking;
use App\Models\Organization;
use App\Models\OrganizationContact;
use App\Models\PaymentRule;
use App\Models\PaymentRefund;
use App\Models\PaymentTransaction;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class PaymentSupportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-02 12:00:00 UTC'));
        Notification::fake();
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_provider_credentials_are_encrypted_at_rest(): void
    {
        $organization = Organization::factory()->create();
        $settings = $organization->paymentSettings()->create([
            'stripe_enabled' => true,
            'stripe_test_mode' => true,
            'stripe_secret_key' => 'sk_test_private',
            'stripe_webhook_secret' => 'whsec_private',
        ]);

        $raw = DB::table('organization_payment_settings')->where('id', $settings->getKey())->first();

        $this->assertNotSame('sk_test_private', $raw->stripe_secret_key);
        $this->assertNotSame('whsec_private', $raw->stripe_webhook_secret);
        $this->assertSame('sk_test_private', $settings->fresh()->stripe_secret_key);
    }

    public function test_blocklist_takes_precedence_and_allowlist_waives_only_prepayment(): void
    {
        $organization = Organization::factory()->create();
        $allow = PaymentRule::create([
            'organization_id' => $organization->getKey(),
            'rule_type' => 'allowlist',
            'match_type' => 'email',
            'pattern' => 'trusted@example.test',
            'is_active' => true,
        ]);
        $block = PaymentRule::create([
            'organization_id' => $organization->getKey(),
            'rule_type' => 'blocklist',
            'match_type' => 'domain',
            'pattern' => 'example.test',
            'is_active' => true,
        ]);

        $blocked = false;
        try {
            app(PaymentRuleService::class)->assertMayBook($organization, 'TRUSTED@example.test');
        } catch (RuntimeException $exception) {
            $blocked = true;
            $this->assertStringContainsString('cannot make an online booking', $exception->getMessage());
        }
        $this->assertTrue($blocked, 'A matching blocklist rule should take precedence.');

        $block->update(['is_active' => false]);
        $matched = app(PaymentRuleService::class)->assertMayBook($organization, 'TRUSTED@example.test');
        $this->assertTrue($matched->is($allow));
    }

    public function test_percentage_retainer_and_refund_terms_are_snapshotted(): void
    {
        $organization = Organization::factory()->create(['timezone' => 'America/Toronto']);
        $type = $this->appointmentType($organization, [
            'payment_collection_mode' => 'retainer',
            'retainer_type' => 'percentage',
            'retainer_percentage_bps' => 2550,
            'balance_due_value' => 2,
            'balance_due_unit' => 'day',
            'client_refund_percentage_bps' => 5000,
            'staff_refund_percentage_bps' => 10000,
        ]);
        $start = CarbonImmutable::parse('2026-09-15 09:00:00', 'America/Toronto')->utc();

        $snapshot = app(BookingPaymentSnapshotService::class)->snapshot($type, 10001, $start, false, null);

        $this->assertSame('retainer', $snapshot['payment_collection_mode']);
        $this->assertSame(2550, $snapshot['initial_payment_due_minor']);
        $this->assertSame('2026-09-13 09:00', $snapshot['balance_due_at_utc']->setTimezone('America/Toronto')->format('Y-m-d H:i'));
        $this->assertSame(5000, $snapshot['client_refund_percentage_bps']);
        $this->assertSame(10000, $snapshot['staff_refund_percentage_bps']);
    }

    public function test_exact_capture_confirms_booking_and_rejects_provider_amount_mismatch(): void
    {
        $booking = $this->paidBooking();
        $payment = $this->payment($booking);
        $state = app(PaymentStateService::class);

        try {
            $state->complete($payment, 'pi_bad', 9999, 'CAD', []);
            $this->fail('A mismatched provider amount should be rejected.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('does not match', $exception->getMessage());
        }
        $this->assertSame(PaymentTransactionStatus::Pending, $payment->fresh()->status);

        $completed = $state->complete($payment, 'pi_exact', 10000, 'cad', ['id' => 'cs_exact']);
        $this->assertSame('confirmed', $completed->status->value);
        $this->assertSame('paid', $completed->payment_status->value);
        $this->assertSame(10000, $completed->paid_minor);
    }

    public function test_organization_specific_stripe_and_paypal_checkouts_replace_stale_open_checkout(): void
    {
        $booking = $this->paidBooking();
        $booking->organization->paymentSettings()->create([
            'default_provider' => 'stripe',
            'stripe_enabled' => true,
            'stripe_test_mode' => true,
            'stripe_secret_key' => 'sk_test_private',
            'stripe_webhook_secret' => 'whsec_private',
            'paypal_enabled' => true,
            'paypal_sandbox' => true,
            'paypal_client_id' => 'paypal-client',
            'paypal_client_secret' => 'paypal-secret',
            'paypal_webhook_id' => 'paypal-webhook',
        ]);
        Http::fake([
            'https://api.stripe.com/v1/checkout/sessions' => Http::response([
                'id' => 'cs_checkout',
                'url' => 'https://checkout.stripe.test/session',
                'status' => 'open',
                'payment_intent' => null,
            ]),
            'https://api-m.sandbox.paypal.com/v1/oauth2/token' => Http::response([
                'access_token' => 'paypal-access-token',
            ]),
            'https://api-m.sandbox.paypal.com/v2/checkout/orders' => Http::response([
                'id' => 'paypal-order',
                'status' => 'CREATED',
                'links' => [['rel' => 'payer-action', 'href' => 'https://paypal.test/approve']],
            ]),
        ]);

        $checkouts = app(PaymentCheckoutService::class);
        $stripe = $checkouts->start($booking, PaymentProvider::Stripe, PaymentPurpose::Initial);
        $paypal = $checkouts->start($booking->fresh(['organization']), PaymentProvider::PayPal, PaymentPurpose::Initial);

        $this->assertSame('https://checkout.stripe.test/session', $stripe->checkout_url);
        $this->assertSame(PaymentTransactionStatus::Cancelled, $stripe->fresh()->status);
        $this->assertSame('https://paypal.test/approve', $paypal->checkout_url);
        $this->assertSame(PaymentTransactionStatus::Pending, $paypal->status);
        Http::assertSentCount(3);
    }

    public function test_stripe_webhook_signature_is_verified_before_payload_is_accepted(): void
    {
        $organization = Organization::factory()->create();
        $settings = $organization->paymentSettings()->create([
            'stripe_enabled' => true,
            'stripe_test_mode' => true,
            'stripe_secret_key' => 'sk_test_private',
            'stripe_webhook_secret' => 'whsec_private',
        ]);
        $payload = json_encode(['id' => 'evt_1', 'type' => 'checkout.session.completed'], JSON_THROW_ON_ERROR);
        $timestamp = CarbonImmutable::now('UTC')->timestamp;
        $signature = hash_hmac('sha256', $timestamp.'.'.$payload, 'whsec_private');

        $event = app(StripePaymentGateway::class)->verifyWebhook(
            $settings,
            $payload,
            't='.$timestamp.',v1='.$signature,
        );

        $this->assertSame('evt_1', $event['id']);

        $this->expectException(RuntimeException::class);
        app(StripePaymentGateway::class)->verifyWebhook($settings, $payload, 't='.$timestamp.',v1=invalid');
    }

    public function test_webhook_endpoint_rejects_an_empty_verification_configuration(): void
    {
        $organization = Organization::factory()->create();
        $organization->paymentSettings()->create();
        $payload = json_encode(['id' => 'evt_empty', 'type' => 'checkout.session.completed'], JSON_THROW_ON_ERROR);
        $timestamp = CarbonImmutable::now('UTC')->timestamp;
        $signature = hash_hmac('sha256', $timestamp.'.'.$payload, '');

        $this->call(
            'POST',
            route('payments.webhooks', [$organization, PaymentProvider::Stripe->value]),
            server: ['HTTP_STRIPE_SIGNATURE' => 't='.$timestamp.',v1='.$signature],
            content: $payload,
        )->assertNotFound();
    }

    public function test_automatic_cancellation_refund_is_idempotent(): void
    {
        $booking = $this->paidBooking([
            'status' => 'cancelled',
            'cancellation_origin' => 'client',
            'cancelled_at_utc' => now('UTC'),
            'client_refund_percentage_bps' => 5000,
            'paid_minor' => 10000,
            'payment_status' => 'paid',
        ]);
        $payment = $this->payment($booking, [
            'status' => 'succeeded',
            'provider_capture_id' => 'pi_refundable',
            'completed_at_utc' => now('UTC'),
        ]);
        $booking->organization->paymentSettings()->create([
            'stripe_enabled' => true,
            'stripe_test_mode' => true,
            'stripe_secret_key' => 'sk_test_private',
            'stripe_webhook_secret' => 'whsec_private',
        ]);
        Http::fake([
            'https://api.stripe.com/v1/refunds' => Http::response(['id' => 're_1', 'status' => 'succeeded'], 200),
        ]);

        $service = app(PaymentRefundService::class);
        $service->refundForCancellation($booking);
        $service->refundForCancellation($booking->fresh());

        $this->assertSame(1, $payment->refunds()->count());
        $refund = $payment->refunds()->firstOrFail();
        $this->assertSame(PaymentRefundStatus::Succeeded, $refund->status);
        $this->assertSame(5000, $refund->amount_minor);
        $this->assertSame(5000, $booking->fresh()->refunded_minor);
        Http::assertSentCount(1);
    }

    public function test_expired_initial_payment_window_cancels_booking_and_open_checkout(): void
    {
        $booking = $this->paidBooking(['expires_at_utc' => now('UTC')->subMinute()]);
        $payment = $this->payment($booking, ['checkout_url' => 'https://checkout.example.test']);

        $this->artisan('appointments:expire-pending-bookings')->assertSuccessful();

        $this->assertSame('cancelled', $booking->fresh()->status->value);
        $this->assertSame('payment_timeout', $booking->fresh()->cancellation_origin);
        $this->assertSame(PaymentTransactionStatus::Cancelled, $payment->fresh()->status);
        $this->assertNull($payment->fresh()->checkout_url);
    }

    public function test_duplicate_retainer_capture_is_refunded_instead_of_becoming_an_unintended_balance_payment(): void
    {
        $booking = $this->paidBooking([
            'payment_collection_mode' => 'retainer',
            'initial_payment_due_minor' => 2500,
            'status' => 'confirmed',
        ]);
        $booking->organization->paymentSettings()->create([
            'stripe_enabled' => true,
            'stripe_test_mode' => true,
            'stripe_secret_key' => 'sk_test_private',
            'stripe_webhook_secret' => 'whsec_private',
        ]);
        $this->payment($booking, [
            'status' => 'succeeded',
            'amount_minor' => 2500,
            'provider_capture_id' => 'pi_first',
            'completed_at_utc' => now('UTC')->subMinute(),
        ]);
        $duplicate = $this->payment($booking, [
            'status' => 'succeeded',
            'amount_minor' => 2500,
            'provider_capture_id' => 'pi_duplicate',
            'completed_at_utc' => now('UTC'),
        ]);
        Http::fake([
            'https://api.stripe.com/v1/refunds' => Http::response(['id' => 're_duplicate', 'status' => 'succeeded']),
        ]);

        app(PaymentRefundService::class)->refundOverpayment($duplicate);
        app(PaymentRefundService::class)->refundOverpayment($duplicate->fresh());

        $refund = $duplicate->refunds()->firstOrFail();
        $this->assertSame(2500, $refund->amount_minor);
        $this->assertSame(PaymentRefundStatus::Succeeded, $refund->status);
        $this->assertSame(1, $duplicate->refunds()->count());
    }

    public function test_duplicate_refund_does_not_consume_the_cancellation_refund_entitlement(): void
    {
        $booking = $this->paidBooking([
            'status' => 'cancelled',
            'cancellation_origin' => 'client',
            'cancelled_at_utc' => now('UTC'),
            'client_refund_percentage_bps' => 5000,
            'paid_minor' => 20000,
            'payment_status' => 'paid',
        ]);
        $booking->organization->paymentSettings()->create([
            'stripe_enabled' => true,
            'stripe_test_mode' => true,
            'stripe_secret_key' => 'sk_test_private',
            'stripe_webhook_secret' => 'whsec_private',
        ]);
        $first = $this->payment($booking, [
            'status' => 'succeeded',
            'provider_capture_id' => 'pi_first',
            'completed_at_utc' => now('UTC')->subMinute(),
        ]);
        $duplicate = $this->payment($booking, [
            'status' => 'succeeded',
            'provider_capture_id' => 'pi_duplicate',
            'completed_at_utc' => now('UTC'),
        ]);
        Http::fake([
            'https://api.stripe.com/v1/refunds' => Http::sequence()
                ->push(['id' => 're_duplicate', 'status' => 'succeeded'])
                ->push(['id' => 're_policy', 'status' => 'succeeded']),
        ]);

        $service = app(PaymentRefundService::class);
        $service->refundOverpayment($duplicate);
        $service->refundForCancellation($booking->fresh());

        $this->assertSame(10000, (int) $duplicate->refunds()->sum('amount_minor'));
        $this->assertSame(5000, (int) $first->refunds()->sum('amount_minor'));
        $this->assertSame(15000, (int) $booking->refunds()->sum('amount_minor'));
        Http::assertSentCount(2);
    }

    public function test_out_of_order_webhook_cannot_regress_a_succeeded_refund(): void
    {
        $booking = $this->paidBooking();
        $payment = $this->payment($booking, [
            'status' => 'succeeded',
            'provider_capture_id' => 'pi_refunded',
            'completed_at_utc' => now('UTC'),
        ]);
        $refund = PaymentRefund::create([
            'organization_id' => $booking->organization_id,
            'booking_id' => $booking->getKey(),
            'payment_transaction_id' => $payment->getKey(),
            'provider' => 'stripe',
            'status' => 'succeeded',
            'amount_minor' => 10000,
            'currency' => 'CAD',
            'idempotency_key' => (string) Str::uuid(),
            'provider_refund_id' => 're_terminal',
            'completed_at_utc' => now('UTC'),
        ]);

        app(PaymentWebhookService::class)->process($booking->organization, PaymentProvider::Stripe, [
            'id' => 'evt_late_refund_failure',
            'type' => 'refund.failed',
            'data' => ['object' => ['id' => 're_terminal', 'status' => 'failed']],
        ]);

        $this->assertSame(PaymentRefundStatus::Succeeded, $refund->fresh()->status);
    }

    private function paidBooking(array $overrides = []): Booking
    {
        $organization = Organization::factory()->create(['timezone' => 'America/Toronto', 'currency' => 'CAD']);
        $type = $this->appointmentType($organization);
        $appointment = Appointment::create([
            'organization_id' => $organization->getKey(),
            'appointment_type_id' => $type->getKey(),
            'starts_at_utc' => now('UTC')->addWeek(),
            'ends_at_utc' => now('UTC')->addWeek()->addHour(),
            'blocked_starts_at_utc' => now('UTC')->addWeek(),
            'blocked_ends_at_utc' => now('UTC')->addWeek()->addHour(),
            'scheduling_timezone' => 'America/Toronto',
            'duration_value' => 60,
            'capacity' => 1,
            'status' => 'scheduled',
        ]);
        $contact = OrganizationContact::create([
            'organization_id' => $organization->getKey(),
            'first_name' => 'Payment',
            'last_name' => 'Client',
            'email' => 'payment@example.test',
        ]);

        return Booking::create(array_replace([
            'organization_id' => $organization->getKey(),
            'appointment_id' => $appointment->getKey(),
            'appointment_type_id' => $type->getKey(),
            'organization_contact_id' => $contact->getKey(),
            'reference' => Str::upper(Str::random(12)),
            'status' => 'pending_payment',
            'attendee_count' => 1,
            'booking_timezone' => 'America/Toronto',
            'base_price_minor' => 10000,
            'price_minor' => 10000,
            'currency' => 'CAD',
            'payment_collection_mode' => 'full',
            'initial_payment_due_minor' => 10000,
            'client_refund_percentage_bps' => 0,
            'staff_refund_percentage_bps' => 10000,
            'payment_status' => 'unpaid',
            'first_name' => 'Payment',
            'last_name' => 'Client',
            'email' => 'payment@example.test',
            'email_normalized' => 'payment@example.test',
            'email_verified_at' => now('UTC'),
            'manage_token_hash' => hash('sha256', 'manage-token', true),
        ], $overrides))->load(['organization', 'appointmentType', 'appointment']);
    }

    private function appointmentType(Organization $organization, array $overrides = []): AppointmentType
    {
        return AppointmentType::create(array_replace([
            'organization_id' => $organization->getKey(),
            'name' => 'Paid Consultation',
            'slug' => 'paid-consultation-'.Str::lower(Str::random(6)),
            'visibility' => 'public',
            'attendance_mode' => 'single',
            'capacity' => 1,
            'duration_mode' => 'fixed',
            'duration_unit' => 'minute',
            'duration_value' => 60,
            'start_interval_minutes' => 60,
            'buffer_before_minutes' => 0,
            'buffer_after_minutes' => 0,
            'pricing_mode' => 'fixed',
            'fixed_price_minor' => 10000,
            'payment_collection_mode' => 'full',
            'email_verification_mode' => 'none',
            'is_active' => true,
        ], $overrides));
    }

    private function payment(Booking $booking, array $overrides = []): PaymentTransaction
    {
        return PaymentTransaction::create(array_replace([
            'organization_id' => $booking->organization_id,
            'booking_id' => $booking->getKey(),
            'provider' => 'stripe',
            'purpose' => 'initial',
            'status' => 'pending',
            'amount_minor' => 10000,
            'currency' => 'CAD',
            'idempotency_key' => (string) Str::uuid(),
            'return_token_hash' => hash('sha256', Str::random(64), true),
            'provider_external_id' => 'cs_'.Str::lower(Str::random(12)),
            'expires_at_utc' => now('UTC')->addHour(),
        ], $overrides));
    }
}
