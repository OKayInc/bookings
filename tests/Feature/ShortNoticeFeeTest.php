<?php

namespace Tests\Feature;

use App\Domain\Availability\AvailabilityScheduleService;
use App\Domain\Bookings\BookingCreationService;
use App\Domain\Bookings\PublicBookingHoldService;
use App\Domain\Questionnaires\QuestionnairePricingService;
use App\Enums\AvailabilityScope;
use App\Enums\MembershipRole;
use App\Enums\MembershipStatus;
use App\Models\AppointmentType;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShortNoticeFeeTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_owner_can_configure_fixed_and_percentage_short_notice_fees(): void
    {
        [$user, $organization] = $this->ownerContext();

        $response = $this->actingAs($user)
            ->withSession(['active_organization_uuid' => $organization->uuid])
            ->post(route('appointment-types.store'), array_merge($this->validTypePayload(), [
                'short_notice_fees' => [
                    [
                        'threshold_value' => 6,
                        'threshold_unit' => 'hour',
                        'adjustment_type' => 'fixed',
                        'fixed_amount' => '45.25',
                    ],
                    [
                        'threshold_value' => 2,
                        'threshold_unit' => 'day',
                        'adjustment_type' => 'percentage',
                        'percentage' => '17.5',
                    ],
                ],
            ]));

        $response->assertSessionHasNoErrors()->assertRedirect(route('appointment-types.index'));
        $type = AppointmentType::where('name', 'Short-notice session')->firstOrFail();
        $rules = $type->shortNoticeFeeRules()->get();

        $this->assertCount(2, $rules);
        $this->assertSame(4525, $rules[0]->fixed_amount_minor);
        $this->assertNull($rules[0]->percentage_bps);
        $this->assertSame(1750, $rules[1]->percentage_bps);
        $this->assertNull($rules[1]->fixed_amount_minor);

        $editResponse = $this->get(route('appointment-types.edit', $type));
        $editResponse->assertOk();
        $editResponse->assertSee('name="short_notice_fees[0][fixed_amount]"', false);
        $editResponse->assertSee('value="45.25"', false);
        $editResponse->assertSee('name="short_notice_fees[1][percentage]"', false);
        $editResponse->assertSee('value="17.5"', false);
    }

    public function test_duplicate_short_notice_threshold_is_rejected(): void
    {
        [$user, $organization] = $this->ownerContext();

        $response = $this->actingAs($user)
            ->withSession(['active_organization_uuid' => $organization->uuid])
            ->post(route('appointment-types.store'), array_merge($this->validTypePayload(), [
                'short_notice_fees' => [
                    ['threshold_value' => 24, 'threshold_unit' => 'hour', 'adjustment_type' => 'fixed', 'fixed_amount' => '10.00'],
                    ['threshold_value' => 24, 'threshold_unit' => 'hour', 'adjustment_type' => 'percentage', 'percentage' => '10'],
                ],
            ]));

        $response->assertSessionHasErrors('short_notice_fees.1.threshold_value');
        $this->assertSame(0, AppointmentType::count());
    }

    public function test_only_shortest_matching_tier_is_added_after_questionnaire_extras(): void
    {
        $now = CarbonImmutable::parse('2026-08-28 12:00:00', 'UTC');
        $organization = Organization::factory()->create(['timezone' => 'America/Toronto', 'currency' => 'CAD']);
        $type = $this->type($organization);
        $question = $type->questions()->create([
            'type' => 'select', 'label' => 'Extra', 'is_active' => true, 'position' => 1,
        ]);
        $option = $question->options()->create([
            'label' => 'Add-on', 'value' => 'add-on', 'position' => 1, 'is_active' => true,
            'pricing_adjustment_type' => 'fixed', 'pricing_amount_minor' => 2000,
        ]);
        $type->shortNoticeFeeRules()->createMany([
            ['threshold_value' => 72, 'threshold_unit' => 'hour', 'adjustment_type' => 'percentage', 'percentage_bps' => 1000, 'position' => 1, 'is_active' => true],
            ['threshold_value' => 24, 'threshold_unit' => 'hour', 'adjustment_type' => 'percentage', 'percentage_bps' => 2500, 'position' => 2, 'is_active' => true],
            ['threshold_value' => 6, 'threshold_unit' => 'hour', 'adjustment_type' => 'fixed', 'fixed_amount_minor' => 5000, 'position' => 3, 'is_active' => true],
        ]);
        $pricing = app(QuestionnairePricingService::class);
        $answers = [$question->uuid => $option->uuid];

        $withinSix = $pricing->quote($type, 60, $answers, $now->addHours(4), $now);
        $atSix = $pricing->quote($type, 60, $answers, $now->addHours(6), $now);
        $withinDay = $pricing->quote($type, 60, $answers, $now->addHours(12), $now);
        $withinThreeDays = $pricing->quote($type, 60, $answers, $now->addHours(48), $now);
        $outside = $pricing->quote($type, 60, $answers, $now->addHours(96), $now);

        $this->assertSame(17000, $withinSix->totalMinor);
        $this->assertSame(17000, $atSix->totalMinor);
        $this->assertSame(15000, $withinDay->totalMinor); // 25% of the 12,000 subtotal.
        $this->assertSame(13200, $withinThreeDays->totalMinor); // 10% of the 12,000 subtotal.
        $this->assertSame(12000, $outside->totalMinor);
        $this->assertCount(1, collect($withinSix->lines)->where('sourceType', 'short_notice_fee'));
    }

    public function test_booking_snapshots_the_matching_short_notice_fee(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-24 12:00:00', 'UTC'));
        $organization = Organization::factory()->create(['timezone' => 'America/Toronto', 'currency' => 'CAD']);
        $type = $this->type($organization);
        $type->shortNoticeFeeRules()->create([
            'threshold_value' => 8,
            'threshold_unit' => 'day',
            'adjustment_type' => 'fixed',
            'fixed_amount_minor' => 2500,
            'position' => 1,
            'is_active' => true,
        ]);
        app(AvailabilityScheduleService::class)->save(
            $organization,
            AvailabilityScope::Organization,
            $organization,
            'America/Toronto',
            true,
            [['weekday' => 1, 'start_time' => '09:00', 'end_time' => '12:00']],
        );

        $lease = app(PublicBookingHoldService::class)->acquire(
            $type->fresh(['organization', 'resources']),
            CarbonImmutable::parse('2026-08-31 09:00:00', 'America/Toronto')->utc(),
            60,
            'America/Toronto',
            1,
        );
        $result = app(BookingCreationService::class)->createFromHold($lease->token, [
            'first_name' => 'Short', 'last_name' => 'Notice', 'email' => 'short.notice@example.test',
        ]);

        $booking = $result->booking->fresh('priceLines');
        $this->assertSame(10000, $booking->base_price_minor);
        $this->assertSame(12500, $booking->price_minor);
        $line = $booking->priceLines->firstWhere('source_type', 'short_notice_fee');
        $this->assertNotNull($line);
        $this->assertSame(2500, $line->amount_minor);
        $this->assertSame('fixed', $line->line_type);
        $this->assertSame(8, $line->metadata['threshold_value']);
    }

    private function ownerContext(): array
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create(['currency' => 'CAD']);
        OrganizationMembership::create([
            'organization_id' => $organization->getKey(),
            'person_id' => $user->person_id,
            'role' => MembershipRole::Owner,
            'status' => MembershipStatus::Active,
        ]);

        return [$user, $organization];
    }

    private function validTypePayload(): array
    {
        return [
            'name' => 'Short-notice session',
            'visibility' => 'public',
            'attendance_mode' => 'single',
            'duration_mode' => 'fixed',
            'duration_unit' => 'minute',
            'duration_value' => 60,
            'booking_notice_value' => 0,
            'booking_notice_unit' => 'hour',
            'maximum_booking_notice_value' => 365,
            'maximum_booking_notice_unit' => 'day',
            'buffer_before_minutes' => 0,
            'buffer_after_minutes' => 0,
            'pricing_mode' => 'fixed',
            'fixed_price' => '100.00',
            'is_active' => '1',
        ];
    }

    private function type(Organization $organization): AppointmentType
    {
        return AppointmentType::create([
            'organization_id' => $organization->getKey(),
            'name' => 'Short-notice pricing',
            'slug' => 'short-notice-pricing',
            'visibility' => 'public',
            'attendance_mode' => 'single',
            'capacity' => 1,
            'duration_mode' => 'fixed',
            'duration_unit' => 'minute',
            'duration_value' => 60,
            'start_interval_minutes' => 60,
            'booking_notice_value' => 0,
            'booking_notice_unit' => 'hour',
            'maximum_booking_notice_value' => 365,
            'maximum_booking_notice_unit' => 'day',
            'buffer_before_minutes' => 0,
            'buffer_after_minutes' => 0,
            'pricing_mode' => 'fixed',
            'fixed_price_minor' => 10000,
            'email_verification_mode' => 'none',
            'is_active' => true,
        ]);
    }
}
