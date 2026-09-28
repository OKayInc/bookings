<?php

namespace Tests\Feature;

use App\Domain\Customers\CustomerReputationService;
use App\Domain\Customers\PostAppointmentReviewService;
use App\Domain\Payments\OfflineBookingPaymentService;
use App\Domain\Payments\PaymentProviderCatalog;
use App\Domain\Payments\PaymentRefundService;
use App\Enums\BookingStatus;
use App\Enums\MembershipRole;
use App\Enums\MembershipStatus;
use App\Enums\PaymentProvider;
use App\Enums\PaymentRefundStatus;
use App\Models\Appointment;
use App\Models\AppointmentType;
use App\Models\Booking;
use App\Models\CustomerAccessEntry;
use App\Models\Organization;
use App\Models\OrganizationContact;
use App\Models\OrganizationMembership;
use App\Models\OrganizationPaymentSetting;
use App\Models\PaymentRefund;
use App\Models\User;
use App\Notifications\BalancePaymentExtensionEmail;
use App\Notifications\BookingStatusChangedEmail;
use App\Notifications\PostAppointmentOutcomeReviewEmail;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class OfflineBookingPaymentsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->travelTo(CarbonImmutable::parse('2026-10-01 12:00:00', 'UTC'));
    }

    public function test_deadline_is_per_type_snapshotted_and_not_reset_by_reselection(): void
    {
        $context = $this->context();
        $booking = $this->booking($context);
        $selected = $this->service()->select($booking);
        $this->assertTrue($selected->expires_at_utc->equalTo(now('UTC')->addDay()));
        $this->travel(30)->minutes();
        $context[2]->forceFill(['offline_payment_window_minutes' => 4320, 'offline_payment_instructions' => 'Changed'])->save();
        $again = $this->service()->select($booking);
        $this->assertTrue($again->expires_at_utc->equalTo($selected->expires_at_utc));
        $this->assertSame('Send your transfer to payments@example.test.', $again->offline_payment_instructions);
    }

    public function test_offline_deadline_never_extends_past_the_appointment_start(): void
    {
        $booking = $this->booking($this->context(), CarbonImmutable::now('UTC')->addMinutes(20));
        $selected = $this->service()->select($booking);
        $this->assertTrue($selected->expires_at_utc->equalTo($booking->appointment->starts_at_utc));
    }

    public function test_reference_submission_is_not_payment_and_does_not_extend_the_deadline(): void
    {
        $booking = $this->service()->select($this->booking($this->context()));
        $action = $this->service()->submitReference($booking, 'CA-TRANSFER-123', $this->key());
        $same = $this->service()->submitReference($booking, ' ca-transfer-123 ', $this->key());
        $this->assertTrue($action->is($same));
        $this->assertSame(0, $booking->fresh()->netPaidMinor());
        $this->assertSame(0, $booking->payments()->count());
        $this->assertSame(BookingStatus::PendingPayment, $booking->fresh()->status);
        $this->assertTrue($booking->expires_at_utc->equalTo($booking->fresh()->expires_at_utc));
    }

    public function test_verified_twenty_dollar_retainer_leaves_thirty_dollars_and_confirms_booking(): void
    {
        $context = $this->context();
        $booking = $this->service()->select($this->booking($context));
        $action = $this->service()->submitReference($booking, 'RETAINER-20', $this->key());
        $key = $this->key();
        $receipt = $this->service()->recordReceipt($booking, 2000, $context[0]->person, $key, null, $action->uuid);
        $again = $this->service()->recordReceipt($booking, 2000, $context[0]->person, $key);
        $fresh = $booking->fresh();
        $this->assertTrue($receipt->is($again));
        $this->assertSame(2000, $fresh->netPaidMinor());
        $this->assertSame(3000, $fresh->outstandingMinor());
        $this->assertSame(0, $fresh->initialOutstandingMinor());
        $this->assertSame(BookingStatus::Confirmed, $fresh->status);
        $this->assertNull($fresh->expires_at_utc);
        $this->assertSame($receipt->getKey(), $action->fresh()->payment_transaction_id);
    }

    public function test_small_verified_partial_payment_protects_the_hold_without_falsely_satisfying_retainer(): void
    {
        $context = $this->context();
        $booking = $this->service()->select($this->booking($context));
        $this->service()->recordReceipt($booking, 500, $context[0]->person, $this->key());
        $this->travel(25)->hours();
        $this->artisan('appointments:expire-pending-bookings')->assertExitCode(0);
        $fresh = $booking->fresh();
        $this->assertSame(BookingStatus::PendingPayment, $fresh->status);
        $this->assertSame(1500, $fresh->initialOutstandingMinor());
        $this->assertNull($fresh->expires_at_utc);
    }

    public function test_unverified_expired_transfer_releases_booking_and_emails_explanation_once(): void
    {
        $booking = $this->service()->select($this->booking($this->context()));
        $this->service()->submitReference($booking, 'UNVERIFIED', $this->key());
        $this->travel(25)->hours();
        $this->artisan('appointments:expire-pending-bookings')->assertExitCode(0);
        $this->artisan('appointments:expire-pending-bookings')->assertExitCode(0);
        $this->assertSame(BookingStatus::Cancelled, $booking->fresh()->status);
        $this->assertSame('cancelled', $booking->appointment->fresh()->status->value);
        $this->assertSame('payment_timeout', $booking->fresh()->cancellation_origin);
        $this->assertNotNull($booking->fresh()->payment_expiry_notified_at_utc);
        Notification::assertSentOnDemandTimes(BookingStatusChangedEmail::class, 1);
    }

    public function test_expiring_one_group_booking_does_not_cancel_another_booking(): void
    {
        $context = $this->context();
        $booking = $this->service()->select($this->booking($context));
        $other = $this->booking($context);
        $other->update(['appointment_id' => $booking->appointment_id, 'status' => 'confirmed', 'expires_at_utc' => null]);
        $this->travel(25)->hours();
        $this->artisan('appointments:expire-pending-bookings')->assertExitCode(0);
        $this->assertSame(BookingStatus::Cancelled, $booking->fresh()->status);
        $this->assertSame('scheduled', $booking->appointment->fresh()->status->value);
        $this->assertSame(BookingStatus::Confirmed, $other->fresh()->status);
    }

    public function test_late_receipt_can_be_reconciled_without_restoring_the_released_slot(): void
    {
        $context = $this->context();
        $booking = $this->service()->select($this->booking($context));
        $this->travel(25)->hours();
        $this->artisan('appointments:expire-pending-bookings')->assertExitCode(0);
        $this->service()->recordReceipt($booking, 2000, $context[0]->person, $this->key(), 'LATE-20', null, true);
        $this->assertSame(2000, $booking->fresh()->netPaidMinor());
        $this->assertSame(BookingStatus::Cancelled, $booking->fresh()->status);
        $this->assertSame('cancelled', $booking->appointment->fresh()->status->value);
    }

    public function test_full_receipt_reference_cannot_be_counted_twice_with_different_form_tokens(): void
    {
        $context = $this->context();
        $booking = $this->service()->select($this->booking($context));
        $action = $this->service()->submitReference($booking, 'FULL-50', $this->key());
        $one = $this->service()->recordReceipt($booking, 5000, $context[0]->person, $this->key(), null, $action->uuid);
        $two = $this->service()->recordReceipt($booking, 5000, $context[0]->person, $this->key(), null, $action->uuid);
        $this->assertTrue($one->is($two));
        $this->assertSame(5000, $booking->fresh()->netPaidMinor());
        $this->assertSame(1, $booking->payments()->count());
    }

    public function test_same_transfer_reference_cannot_pay_two_bookings_in_the_same_organization(): void
    {
        $context = $this->context();
        $one = $this->booking($context);
        $two = $this->booking($context);
        $this->service()->recordReceipt($one, 2000, $context[0]->person, $this->key(), 'SAME-REFERENCE');
        $this->expectException(RuntimeException::class);
        $this->service()->recordReceipt($two, 2000, $context[0]->person, $this->key(), 'same-reference');
    }

    public function test_balance_review_runs_even_when_attendance_is_recorded_and_attendance_email_disabled(): void
    {
        $context = $this->context(false);
        $booking = $this->booking($context, CarbonImmutable::now('UTC')->subHours(2));
        $this->service()->recordReceipt($booking, 2000, $context[0]->person, $this->key());
        app(CustomerReputationService::class)->recordOutcome($booking->fresh(), 'successful', $context[0]->person);
        Notification::fake();
        $reviews = app(PostAppointmentReviewService::class);
        $this->assertSame(1, $reviews->sendDue());
        $this->assertSame(0, $reviews->sendDue());
        Notification::assertSentTo($context[0], PostAppointmentOutcomeReviewEmail::class, function ($notification, $channels) use ($context): bool {
            $mail = $notification->toMail($context[0]);
            return $mail->actionText === 'Review payment' && str_contains(implode(' ', $mail->introLines), 'CAD 30.00');
        });
    }

    public function test_fully_paid_booking_has_no_balance_followup(): void
    {
        $context = $this->context(false);
        $booking = $this->booking($context, CarbonImmutable::now('UTC')->subHours(2));
        $this->service()->recordReceipt($booking, 5000, $context[0]->person, $this->key());
        Notification::fake();
        $this->assertSame(0, app(PostAppointmentReviewService::class)->sendDue());
        Notification::assertNothingSent();
    }

    public function test_extension_notifies_customer_and_reminds_staff_after_seven_days_not_before(): void
    {
        $context = $this->context(false);
        $booking = $this->booking($context, CarbonImmutable::now('UTC')->subHours(2));
        $this->service()->recordReceipt($booking, 2000, $context[0]->person, $this->key());
        $reviews = app(PostAppointmentReviewService::class);
        $this->assertSame(1, $reviews->sendDue());
        $this->service()->extendBalance($booking, 7, $context[0]->person, $this->key());
        Notification::fake();
        $this->service()->sendPendingNotices();
        Notification::assertSentOnDemandTimes(BalancePaymentExtensionEmail::class, 1);
        $this->travel(6)->days();
        $this->assertSame(0, $reviews->sendDue());
        $this->travel(2)->days();
        $this->assertSame(1, $reviews->sendDue());
        $this->assertSame(0, $reviews->sendDue());
        $this->assertSame(0, CustomerAccessEntry::query()->where('list_type', 'blacklist')->count());
    }

    public function test_payment_during_extension_prevents_another_unpaid_reminder(): void
    {
        $context = $this->context(false);
        $booking = $this->booking($context, CarbonImmutable::now('UTC')->subHours(2));
        $this->service()->recordReceipt($booking, 2000, $context[0]->person, $this->key());
        $this->service()->extendBalance($booking, 2, $context[0]->person, $this->key());
        $this->service()->recordReceipt($booking, 3000, $context[0]->person, $this->key());
        $this->travel(3)->days();
        Notification::fake();
        $this->assertSame(0, app(PostAppointmentReviewService::class)->sendDue());
        Notification::assertNothingSent();
    }

    public function test_nonpayment_blacklist_is_manual_and_does_not_change_attendance_or_debt(): void
    {
        $context = $this->context();
        $booking = $this->booking($context, CarbonImmutable::now('UTC')->subHours(2));
        $this->service()->recordReceipt($booking, 2000, $context[0]->person, $this->key());
        app(CustomerReputationService::class)->recordOutcome($booking->fresh(), 'successful', $context[0]->person);
        $key = $this->key();
        $this->service()->blacklistForNonPayment($booking, $context[0]->person, $key);
        $this->service()->blacklistForNonPayment($booking, $context[0]->person, $key);
        $entry = CustomerAccessEntry::query()->where('organization_contact_id', $context[3]->getKey())->firstOrFail();
        $this->assertSame('manual', $entry->source);
        $this->assertSame('blacklist', $entry->list_type);
        $this->assertStringContainsString('Non-payment', $entry->reason);
        $this->assertSame('successful', $booking->fresh()->outcome->outcome);
        $this->assertSame(3000, $booking->fresh()->outstandingMinor());
        $this->assertSame(1, CustomerAccessEntry::query()->where('status', 'active')->count());
        $this->service()->recordReceipt($booking, 3000, $context[0]->person, $this->key());
        $this->assertSame('active', $entry->fresh()->status);
    }

    public function test_balance_decisions_are_not_allowed_before_appointment_ends(): void
    {
        $context = $this->context();
        $booking = $this->booking($context);
        $this->expectException(RuntimeException::class);
        $this->service()->extendBalance($booking, 7, $context[0]->person, $this->key());
    }

    public function test_staff_pages_require_membership_and_public_tokens_cannot_mark_payment(): void
    {
        $context = $this->context();
        $booking = $this->booking($context);
        $this->get(route('booking-payment-review.show', $booking))->assertRedirect(route('login'));
        $outsider = User::factory()->create();
        $this->actingAs($outsider)->get(route('booking-payment-review.show', $booking))->assertForbidden();
        $this->actingAs($context[0])->get(route('booking-payment-review.show', $booking))->assertOk()->assertSee('CAD 50.00');
    }

    public function test_public_reference_route_requires_the_correct_booking_manage_token(): void
    {
        $booking = $this->service()->select($this->booking($this->context()));
        $this->post(route('public.offline-payments.reference', [$booking, 'wrong-token']), [
            'reference' => 'FAKE', 'idempotency_key' => $this->key(),
        ])->assertNotFound();
        $this->post(route('public.offline-payments.reference', [$booking, 'test-manage-token']), [
            'reference' => 'CLAIM-ONLY', 'idempotency_key' => $this->key(),
        ])->assertRedirect();
        $this->assertSame(0, $booking->fresh()->netPaidMinor());
    }

    public function test_type_settings_save_individual_deadline_and_staff_pages_render(): void
    {
        $context = $this->context();
        $this->actingAs($context[0])->withSession(['active_organization_uuid' => $context[1]->uuid]);
        $this->get(route('appointment-types.offline-payments.edit', $context[2]))->assertOk();
        $this->put(route('appointment-types.offline-payments.update', $context[2]), [
            'offline_payment_enabled' => '1', 'window_value' => 2, 'window_unit' => 'hour',
            'instructions' => 'Transfer to finance@example.test.',
        ])->assertRedirect();
        $this->assertSame(120, (int) $context[2]->fresh()->offline_payment_window_minutes);
        $this->get(route('booking-payment-review.index'))->assertOk();
    }

    public function test_offline_is_never_an_online_gateway_and_refunds_need_manual_confirmation(): void
    {
        $context = $this->context();
        $settings = new OrganizationPaymentSetting();
        $this->assertFalse($settings->isConfigured(PaymentProvider::Offline));
        $this->assertFalse($settings->hasCredentials(PaymentProvider::Offline));
        $this->assertSame([], app(PaymentProviderCatalog::class)->available($context[1]));
        $booking = $this->booking($context);
        $receipt = $this->service()->recordReceipt($booking, 2000, $context[0]->person, $this->key());
        $refund = PaymentRefund::create([
            'organization_id' => $booking->organization_id, 'booking_id' => $booking->getKey(),
            'payment_transaction_id' => $receipt->getKey(), 'provider' => 'offline', 'status' => 'pending',
            'refund_type' => 'general', 'amount_minor' => 1000, 'currency' => 'CAD', 'idempotency_key' => $this->key(),
        ]);
        Http::fake();
        $pending = app(PaymentRefundService::class)->send($refund);
        $this->assertSame(PaymentRefundStatus::Pending, $pending->status);
        Http::assertNothingSent();
        $this->service()->recordRefund($booking, $refund->uuid, $context[0]->person, $this->key(), 'RETURN-10');
        $this->assertSame(PaymentRefundStatus::Succeeded, $refund->fresh()->status);
        $this->assertSame(1000, $booking->fresh()->netPaidMinor());
    }

    private function service(): OfflineBookingPaymentService { return app(OfflineBookingPaymentService::class); }
    private function key(): string { return (string) Str::uuid(); }

    private function context(bool $attendance = true): array
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create(['currency' => 'CAD', 'timezone' => 'America/Toronto']);
        OrganizationMembership::create(['organization_id' => $organization->getKey(), 'person_id' => $user->person_id,
            'role' => MembershipRole::Owner, 'status' => MembershipStatus::Active]);
        $organization->customerReputationSetting()->create([
            'post_appointment_review_enabled' => $attendance, 'review_roles' => ['owner'],
            'blacklist_mode' => 'disabled', 'blacklist_no_show_threshold' => 2, 'blacklist_window_days' => 180,
            'whitelist_mode' => 'disabled', 'whitelist_success_threshold' => 5, 'whitelist_min_revenue_minor' => 0,
            'whitelist_window_days' => 365, 'whitelist_max_no_shows' => 0, 'minimum_reviewed_appointments' => 2,
        ]);
        $type = AppointmentType::create([
            'organization_id' => $organization->getKey(), 'name' => 'Appointment A', 'slug' => 'appointment-a',
            'visibility' => 'public', 'attendance_mode' => 'single', 'capacity' => 1, 'duration_mode' => 'fixed',
            'duration_unit' => 'minute', 'duration_value' => 60, 'start_interval_minutes' => 60,
            'buffer_before_minutes' => 0, 'buffer_after_minutes' => 0, 'pricing_mode' => 'fixed',
            'fixed_price_minor' => 5000, 'payment_collection_mode' => 'retainer', 'retainer_type' => 'fixed',
            'retainer_amount_minor' => 2000, 'email_verification_mode' => 'none', 'is_active' => true,
        ]);
        $type->forceFill(['offline_payment_enabled' => true, 'offline_payment_window_minutes' => 1440,
            'offline_payment_instructions' => 'Send your transfer to payments@example.test.'])->save();
        $contact = OrganizationContact::create(['organization_id' => $organization->getKey(),
            'first_name' => 'Offline', 'last_name' => 'Customer', 'email' => 'offline@example.test', 'phone' => '+1 613 555 0123']);
        return [$user, $organization, $type, $contact];
    }

    private function booking(array $context, ?CarbonImmutable $start = null): Booking
    {
        [, $organization, $type, $contact] = $context;
        $start ??= CarbonImmutable::now('UTC')->addDays(3);
        $appointment = Appointment::create([
            'organization_id' => $organization->getKey(), 'appointment_type_id' => $type->getKey(),
            'starts_at_utc' => $start, 'ends_at_utc' => $start->addHour(),
            'blocked_starts_at_utc' => $start, 'blocked_ends_at_utc' => $start->addHour(),
            'scheduling_timezone' => 'America/Toronto', 'duration_value' => 60, 'capacity' => 1, 'status' => 'scheduled',
        ]);
        return Booking::create([
            'organization_id' => $organization->getKey(), 'appointment_id' => $appointment->getKey(),
            'appointment_type_id' => $type->getKey(), 'organization_contact_id' => $contact->getKey(),
            'reference' => Str::upper(Str::random(12)), 'status' => 'pending_payment',
            'attendee_count' => 1, 'booking_timezone' => 'America/Toronto', 'base_price_minor' => 5000,
            'price_minor' => 5000, 'currency' => 'CAD', 'payment_collection_mode' => 'retainer',
            'initial_payment_due_minor' => 2000, 'expires_at_utc' => now('UTC')->addHour(),
            'first_name' => 'Offline', 'last_name' => 'Customer', 'email' => 'offline@example.test',
            'email_normalized' => 'offline@example.test', 'phone' => '+1 613 555 0123',
            'manage_token_hash' => hash('sha256', 'test-manage-token', true),
        ]);
    }
}
