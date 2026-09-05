<?php

namespace Tests\Feature;

use App\Domain\Availability\AvailabilityScheduleService;
use App\Domain\Bookings\BookingCreationService;
use App\Domain\Bookings\BookingWorkflowService;
use App\Domain\Coupons\CouponRedemptionService;
use App\Domain\Bookings\PublicBookingHoldService;
use App\Domain\Questionnaires\QuestionnairePricingService;
use App\Domain\Questionnaires\QuestionnaireSubmission;
use App\Domain\Payments\PaymentRefundService;
use App\Domain\Resources\ResourceDepositService;
use App\Enums\AvailabilityScope;
use App\Enums\MembershipRole;
use App\Enums\MembershipStatus;
use App\Enums\BookingStatus;
use App\Enums\CouponDiscountType;
use App\Enums\CouponSource;
use App\Enums\CouponStatus;
use App\Enums\PaymentRefundType;
use App\Models\Appointment;
use App\Models\AppointmentQuestion;
use App\Models\AppointmentType;
use App\Models\Booking;
use App\Models\Coupon;
use App\Models\Organization;
use App\Models\OrganizationContact;
use App\Models\OrganizationMembership;
use App\Models\PaymentRefund;
use App\Models\PaymentTransaction;
use App\Models\Resource;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class M9R6ResourceDepositTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-05 12:00:00 UTC'));
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_equipment_without_a_linked_person_can_store_a_default_deposit(): void
    {
        [$user, $organization] = $this->organizationContext();

        $this->actingAs($user)
            ->withSession(['active_organization_uuid' => $organization->uuid])
            ->post(route('resources.store'), [
                'name' => 'Rental projector',
                'type' => 'equipment',
                'quantity_enabled' => '1',
                'inventory_quantity' => 3,
                'default_deposit' => '125.00',
                'timezone' => 'UTC',
                'default_requirement' => 'required',
                'is_active' => '1',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('resources.index'));

        $resource = Resource::where('name', 'Rental projector')->firstOrFail();
        $this->assertNull($resource->person_id);
        $this->assertSame(12500, $resource->deposit_amount_minor);
    }

    public function test_question_assignment_overrides_fallback_and_explicit_zero_are_applied(): void
    {
        $organization = Organization::factory()->create(['timezone' => 'UTC', 'currency' => 'CAD']);
        $type = $this->appointmentType($organization, ['pricing_mode' => 'free', 'fixed_price_minor' => null]);
        $first = $this->equipment($organization, 'Lights', 10, 1000);
        $second = $this->equipment($organization, 'Microphones', 10, 700);
        $waived = $this->equipment($organization, 'Cables', 10, 500);
        foreach ([$first, $second, $waived] as $resource) {
            $type->resources()->attach($resource->getKey(), [
                'is_required' => false,
                'requirement_mode' => 'optional',
                'quantity_required' => $resource->is($first) ? 2 : 1,
            ]);
        }
        $question = $this->conditionalQuestion($type);
        $rule = $question->resourceRequirementRule;
        $rule->resources()->sync([
            $first->getKey() => ['deposit_amount_minor' => null],
            $second->getKey() => ['deposit_amount_minor' => 250],
            $waived->getKey() => ['deposit_amount_minor' => 0],
        ]);

        $charges = app(ResourceDepositService::class)->charges(
            $type->fresh(),
            [$question->uuid => $rule->triggerOption->uuid],
            [
                $first->getKey() => 2,
                $second->getKey() => 1,
                $waived->getKey() => 1,
            ],
        );

        $this->assertSame(2250, collect($charges)->sum('amountMinor'));
        $this->assertSame(2000, collect($charges)->firstWhere('resourceUuid', $first->uuid)->amountMinor);
        $this->assertSame('resource_default', collect($charges)->firstWhere('resourceUuid', $first->uuid)->configurationSource);
        $this->assertSame(250, collect($charges)->firstWhere('resourceUuid', $second->uuid)->amountMinor);
        $this->assertSame('question_override', collect($charges)->firstWhere('resourceUuid', $second->uuid)->configurationSource);
        $this->assertNull(collect($charges)->firstWhere('resourceUuid', $waived->uuid));
    }

    public function test_deposit_is_snapshotted_and_collected_in_full_on_top_of_a_retainer(): void
    {
        $organization = Organization::factory()->create(['timezone' => 'UTC', 'currency' => 'CAD']);
        $type = $this->appointmentType($organization, [
            'payment_collection_mode' => 'retainer',
            'retainer_type' => 'percentage',
            'retainer_percentage_bps' => 2500,
        ]);
        $projectors = $this->equipment($organization, 'Projectors', 5, 1000);
        $type->resources()->attach($projectors->getKey(), [
            'is_required' => true,
            'requirement_mode' => 'required',
            'quantity_required' => 2,
            'equipment_pricing_mode' => 'free',
        ]);
        $this->schedule($organization);

        $lease = app(PublicBookingHoldService::class)->acquire(
            $type->fresh(['organization', 'resources']),
            CarbonImmutable::parse('2026-09-07 09:00:00 UTC'),
            60,
            'UTC',
            1,
        );
        $booking = app(BookingCreationService::class)->createFromHold($lease->token, [
            'first_name' => 'Equipment',
            'last_name' => 'Renter',
            'email' => 'renter@example.test',
        ])->booking->fresh(['resourceDeposits', 'appointment.resources']);

        $this->assertNull($projectors->person_id);
        $this->assertSame(10000, $booking->base_price_minor);
        $this->assertSame(12000, $booking->price_minor);
        $this->assertSame(2000, $booking->deposit_minor);
        $this->assertSame(4500, $booking->initial_payment_due_minor);
        $this->assertCount(1, $booking->resourceDeposits);
        $this->assertSame(2, $booking->resourceDeposits->first()->quantity);
        $this->assertSame(1000, $booking->resourceDeposits->first()->unit_amount_minor);
    }

    public function test_partial_and_full_deposit_refunds_require_a_reason_and_use_the_original_capture(): void
    {
        [$user, $organization] = $this->organizationContext();
        [$booking, $payment] = $this->paidDepositBooking($organization);
        $organization->paymentSettings()->create([
            'stripe_enabled' => true,
            'stripe_test_mode' => true,
            'stripe_secret_key' => 'sk_test_private',
            'stripe_webhook_secret' => 'whsec_private',
        ]);

        $this->actingAs($user)
            ->withSession(['active_organization_uuid' => $organization->uuid])
            ->post(route('bookings.deposit-refunds.store', $booking), [
                'refund_mode' => 'partial',
                'amount' => '5.00',
            ])
            ->assertSessionHasErrors('reason');

        Http::fake([
            'https://api.stripe.com/v1/refunds' => Http::sequence()
                ->push(['id' => 're_deposit_partial', 'status' => 'succeeded'])
                ->push(['id' => 're_deposit_full', 'status' => 'succeeded']),
        ]);

        $this->actingAs($user)
            ->withSession(['active_organization_uuid' => $organization->uuid])
            ->post(route('bookings.deposit-refunds.store', $booking), [
                'refund_mode' => 'partial',
                'amount' => '5.00',
                'reason' => 'One cable was returned damaged.',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(500, $booking->fresh()->deposit_refunded_minor);
        $partial = PaymentRefund::query()->firstOrFail();
        $this->assertSame(PaymentRefundType::Deposit, $partial->refund_type);
        $this->assertSame($payment->getKey(), $partial->payment_transaction_id);
        $this->assertStringContainsString('One cable was returned damaged.', $partial->reason);

        $this->actingAs($user)
            ->withSession(['active_organization_uuid' => $organization->uuid])
            ->post(route('bookings.deposit-refunds.store', $booking), [
                'refund_mode' => 'full',
            ])
            ->assertSessionHasNoErrors();

        $booking->refresh();
        $this->assertSame(2000, $booking->deposit_refunded_minor);
        $this->assertSame(7500, $booking->outstandingMinor());
        $this->assertSame(0, app(PaymentRefundService::class)->refundableDepositMinor($booking));
        $this->assertSame(2, PaymentRefund::where('refund_type', 'deposit')->count());
        Http::assertSent(fn ($request): bool => $request->url() === 'https://api.stripe.com/v1/refunds'
            && $request['payment_intent'] === 'pi_original_deposit');
    }

    public function test_ordinary_refund_cannot_consume_money_reserved_for_the_deposit(): void
    {
        [, $organization] = $this->organizationContext();
        [$booking] = $this->paidDepositBooking($organization);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('reserving the refundable deposit');
        app(PaymentRefundService::class)->refundAmount($booking, 3000, 'General price adjustment');
    }

    public function test_coupons_do_not_discount_the_refundable_deposit(): void
    {
        $organization = Organization::factory()->create(['timezone' => 'UTC', 'currency' => 'CAD']);
        $type = $this->appointmentType($organization);
        $resource = $this->equipment($organization, 'Camera kit', 1, 2000);
        $type->resources()->attach($resource->getKey(), [
            'is_required' => true,
            'requirement_mode' => 'required',
            'quantity_required' => 1,
        ]);
        $coupon = Coupon::create([
            'organization_id' => $organization->getKey(),
            'source' => CouponSource::Manual->value,
            'status' => CouponStatus::Active->value,
            'code' => 'DEPOSIT-SAFE',
            'code_hash' => hash('sha256', Coupon::normalizeCode('DEPOSIT-SAFE'), true),
            'discount_type' => CouponDiscountType::Percentage->value,
            'percentage_bps' => 10000,
            'amount_minor' => null,
            'remaining_amount_minor' => null,
            'applies_to_all' => true,
            'recipient_email' => 'renter@example.test',
            'password_hash' => bcrypt('deposit-test-password'),
            'view_token' => Str::random(64),
            'view_token_hash' => hash('sha256', Str::random(64), true),
            'delivery_method' => 'print',
        ]);
        $quote = app(QuestionnairePricingService::class)->quote($type->fresh(), 60, []);
        $application = app(CouponRedemptionService::class)->apply(
            $coupon->code,
            $type,
            new QuestionnaireSubmission([], $quote),
        );

        $this->assertSame(12000, $quote->totalMinor);
        $this->assertSame(10000, $application->discountMinor);
        $this->assertSame(2000, $application->submission->quote->totalMinor);
    }

    public function test_refunded_deposit_does_not_reopen_the_initial_payment_requirement(): void
    {
        [, $organization] = $this->organizationContext();
        [$booking] = $this->paidDepositBooking($organization);
        $booking->forceFill([
            'deposit_refunded_minor' => 2000,
            'refunded_minor' => 2000,
        ])->save();

        $this->assertSame(BookingStatus::Confirmed, app(BookingWorkflowService::class)->statusFor($booking->fresh()));
    }

    private function organizationContext(): array
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create(['timezone' => 'UTC', 'currency' => 'CAD']);
        OrganizationMembership::create([
            'organization_id' => $organization->getKey(),
            'person_id' => $user->person_id,
            'role' => MembershipRole::Owner,
            'status' => MembershipStatus::Active,
        ]);

        return [$user, $organization];
    }

    private function appointmentType(Organization $organization, array $overrides = []): AppointmentType
    {
        return AppointmentType::create(array_replace([
            'organization_id' => $organization->getKey(),
            'name' => 'Equipment rental',
            'slug' => 'equipment-rental-'.Str::lower(Str::random(6)),
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
            'client_refund_percentage_bps' => 0,
            'staff_refund_percentage_bps' => 10000,
            'email_verification_mode' => 'none',
            'is_active' => true,
        ], $overrides));
    }

    private function equipment(Organization $organization, string $name, int $stock, int $deposit): Resource
    {
        return Resource::create([
            'organization_id' => $organization->getKey(),
            'type' => 'equipment',
            'quantity_enabled' => true,
            'inventory_quantity' => $stock,
            'deposit_amount_minor' => $deposit,
            'name' => $name,
            'timezone' => 'UTC',
            'is_active' => true,
            'is_required_by_default' => false,
        ]);
    }

    private function conditionalQuestion(AppointmentType $type): AppointmentQuestion
    {
        $question = $type->questions()->create([
            'type' => 'radio',
            'label' => 'Add equipment?',
            'is_required' => true,
            'is_active' => true,
            'position' => 1,
        ]);
        $no = $question->options()->create(['label' => 'No', 'value' => 'no', 'position' => 0, 'is_active' => true]);
        $yes = $question->options()->create(['label' => 'Yes', 'value' => 'yes', 'position' => 1, 'is_active' => true]);
        $question->resourceRequirementRule()->create([
            'trigger_option_id' => $yes->getKey(),
            'unavailable_default_option_id' => $no->getKey(),
            'group_name' => 'Rental equipment',
            'fulfillment_mode' => 'all',
        ]);

        return $question->fresh(['resourceRequirementRule.triggerOption', 'resourceRequirementRule.resources']);
    }

    private function schedule(Organization $organization): void
    {
        app(AvailabilityScheduleService::class)->save(
            $organization,
            AvailabilityScope::Organization,
            $organization,
            'UTC',
            true,
            [['weekday' => 1, 'start_time' => '08:00', 'end_time' => '17:00']],
        );
    }

    private function paidDepositBooking(Organization $organization): array
    {
        $type = $this->appointmentType($organization, [
            'payment_collection_mode' => 'retainer',
            'retainer_type' => 'fixed',
            'retainer_amount_minor' => 2500,
        ]);
        $appointment = Appointment::create([
            'organization_id' => $organization->getKey(),
            'appointment_type_id' => $type->getKey(),
            'starts_at_utc' => now('UTC')->addWeek(),
            'ends_at_utc' => now('UTC')->addWeek()->addHour(),
            'blocked_starts_at_utc' => now('UTC')->addWeek(),
            'blocked_ends_at_utc' => now('UTC')->addWeek()->addHour(),
            'scheduling_timezone' => 'UTC',
            'duration_value' => 60,
            'capacity' => 1,
            'status' => 'scheduled',
        ]);
        $contact = OrganizationContact::create([
            'organization_id' => $organization->getKey(),
            'first_name' => 'Deposit',
            'last_name' => 'Renter',
            'email' => 'deposit-renter@example.test',
        ]);
        $booking = Booking::create([
            'organization_id' => $organization->getKey(),
            'appointment_id' => $appointment->getKey(),
            'appointment_type_id' => $type->getKey(),
            'organization_contact_id' => $contact->getKey(),
            'reference' => Str::upper(Str::random(12)),
            'status' => 'confirmed',
            'attendee_count' => 1,
            'booking_timezone' => 'UTC',
            'base_price_minor' => 10000,
            'price_minor' => 12000,
            'deposit_minor' => 2000,
            'currency' => 'CAD',
            'payment_collection_mode' => 'retainer',
            'initial_payment_due_minor' => 4500,
            'client_refund_percentage_bps' => 0,
            'staff_refund_percentage_bps' => 10000,
            'payment_status' => 'partially_paid',
            'paid_minor' => 4500,
            'first_name' => 'Deposit',
            'last_name' => 'Renter',
            'email' => 'deposit-renter@example.test',
            'email_normalized' => 'deposit-renter@example.test',
            'email_verified_at' => now('UTC'),
            'manage_token_hash' => hash('sha256', 'manage-token', true),
        ]);
        $payment = PaymentTransaction::create([
            'organization_id' => $organization->getKey(),
            'booking_id' => $booking->getKey(),
            'provider' => 'stripe',
            'purpose' => 'initial',
            'status' => 'succeeded',
            'amount_minor' => 4500,
            'deposit_amount_minor' => 2000,
            'currency' => 'CAD',
            'idempotency_key' => (string) Str::uuid(),
            'return_token_hash' => hash('sha256', Str::random(64), true),
            'provider_external_id' => 'cs_deposit',
            'provider_capture_id' => 'pi_original_deposit',
            'expires_at_utc' => now('UTC')->addHour(),
            'completed_at_utc' => now('UTC'),
        ]);

        return [$booking, $payment];
    }
}
