<?php

namespace Tests\Feature;

use App\Domain\Availability\AvailabilityScheduleService;
use App\Domain\Bookings\BookingCreationService;
use App\Domain\Bookings\PublicBookingAvailabilityService;
use App\Domain\Bookings\PublicBookingHoldService;
use App\Domain\Questionnaires\QuestionnairePricingService;
use App\Domain\Questionnaires\QuestionnaireSubmission;
use App\Enums\AvailabilityScope;
use App\Enums\MembershipRole;
use App\Enums\MembershipStatus;
use App\Models\AppointmentType;
use App\Models\Booking;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use RuntimeException;
use Tests\TestCase;

class PerAttendeePricingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-24 12:00:00', 'UTC'));
        Cache::flush();
        Notification::fake();
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_owner_can_configure_flat_absolute_and_accumulative_attendee_prices(): void
    {
        [$owner, $organization] = $this->ownerContext();
        $this->actingAs($owner)->withSession(['active_organization_uuid' => $organization->uuid]);
        foreach (['flat', 'absolute', 'accumulative'] as $mode) {
            $data = $this->payload($mode);
            $this->post(route('appointment-types.store'), $data)->assertSessionHasNoErrors()->assertRedirect(route('appointment-types.index'));
            $type = AppointmentType::where('name', $data['name'])->firstOrFail();
            $this->assertSame('per_attendee', $type->pricing_mode->value);
            $this->assertSame($mode, $type->attendee_pricing_mode->value);
            if ($mode === 'flat') {
                $this->assertSame(2500, $type->attendee_price_minor);
                $this->assertNull($type->attendee_price_ranges);
            } else {
                $this->assertNull($type->attendee_price_minor);
                $this->assertSame(150, $type->attendee_price_ranges[1]['unit_amount_minor']);
            }
            $this->get(route('appointment-types.edit', $type))->assertOk()
                ->assertSee('Absolute ranges')->assertSee('Accumulative ranges')
                ->assertSee('id="attendee_pricing_mode"', false);
        }
    }

    public function test_single_attendance_cannot_use_per_attendee_pricing_and_switching_clears_old_rules(): void
    {
        [$owner, $organization] = $this->ownerContext();
        $this->actingAs($owner)->withSession(['active_organization_uuid' => $organization->uuid]);
        $data = array_merge($this->payload('flat'), ['attendance_mode' => 'single']);
        $this->post(route('appointment-types.store'), $data)->assertSessionHasErrors('pricing_mode');
        $this->assertDatabaseCount('appointment_types', 0);

        $this->post(route('appointment-types.store'), $this->payload('absolute'))->assertSessionHasNoErrors();
        $type = AppointmentType::firstOrFail();
        $data = array_merge($this->payload('absolute'), ['attendance_mode' => 'single']);
        $this->put(route('appointment-types.update', $type), $data)->assertSessionHasErrors('pricing_mode');
        $data['pricing_mode'] = 'fixed';
        $data['fixed_price'] = '80.00';
        $this->put(route('appointment-types.update', $type), $data)->assertSessionHasNoErrors();
        $type->refresh();
        $this->assertSame(8000, $type->fixed_price_minor);
        $this->assertNull($type->attendee_price_minor);
        $this->assertNull($type->attendee_price_ranges);
    }

    public function test_ranges_with_gaps_overlaps_or_incomplete_capacity_are_rejected(): void
    {
        [$owner, $organization] = $this->ownerContext();
        $this->actingAs($owner)->withSession(['active_organization_uuid' => $organization->uuid]);
        foreach ([2, 10, 12] as $secondStart) {
            $data = $this->payload('absolute');
            $data['attendee_price_ranges'][1]['min_attendees'] = $secondStart;
            $this->post(route('appointment-types.store'), $data)->assertSessionHasErrors('attendee_price_ranges');
        }
        $data = $this->payload('accumulative');
        $data['capacity'] = 21;
        $this->post(route('appointment-types.store'), $data)->assertSessionHasErrors('attendee_price_ranges');
        $data = $this->payload('absolute');
        $data['attendee_price_ranges'][0]['unit_price'] = '0';
        $this->post(route('appointment-types.store'), $data)->assertSessionHasErrors('attendee_price_ranges.0.unit_price');
        $this->assertDatabaseCount('appointment_types', 0);
    }

    public function test_all_attendee_modes_use_held_count_for_preview_quote_and_final_booking(): void
    {
        foreach (['flat' => 30000, 'absolute' => 1800, 'accumulative' => 2300] as $mode => $expected) {
            [$organization, $type] = $this->scheduledType($mode);
            $this->get(route('public.appointment-types.show', [$organization->slug, $type->slug]))
                ->assertOk()->assertSee('including you');
            $slots = $this->getJson(route('public.booking.slots', $type).'?'.http_build_query([
                'access_mode' => 'direct', 'timezone' => 'America/Toronto', 'date' => '2026-08-31',
                'duration_value' => 60, 'attendee_count' => 12,
            ]))->assertOk()->assertJsonPath('price_minor', $expected);
            $this->assertNotEmpty($slots->json('slots'));
            $hold = $this->postJson(route('public.booking.holds.store', $type), [
                'access_mode' => 'direct', 'timezone' => 'America/Toronto',
                'starts_at_utc' => $slots->json('slots.0.starts_at_utc'), 'duration_value' => 60, 'attendee_count' => 12,
            ])->assertOk();
            $token = basename((string) parse_url($hold->json('continue_url'), PHP_URL_PATH));
            $this->get(route('public.booking-holds.edit', $token))->assertOk()->assertSee('id="questionnaire-price-card"', false);
            $this->postJson(route('public.booking-holds.quote', $token), ['attendee_count' => 1, 'price_minor' => 1])
                ->assertOk()->assertJsonPath('base_price_minor', $expected)->assertJsonPath('total_minor', $expected);
            $this->post(route('public.booking-holds.store', $token), [
                'first_name' => 'Photo', 'last_name' => 'Client', 'email' => $mode.'@example.test',
                'attendee_count' => 1, 'price_minor' => 1,
            ])->assertSessionHasNoErrors()->assertRedirect();
            $booking = Booking::where('appointment_type_id', $type->getKey())->firstOrFail();
            $this->assertSame(12, $booking->attendee_count);
            $this->assertSame($expected, $booking->base_price_minor);
            $this->assertSame($expected, $booking->price_minor);
            $lines = $booking->priceLines()->orderBy('position')->get();
            $this->assertSame($expected, (int) $lines->sum('amount_minor'));
            $this->assertSame($mode, $lines->first()->metadata['attendee_pricing_mode']);
            $this->assertCount($mode === 'accumulative' ? 2 : 1, $lines);
            $type->update(['attendee_pricing_mode' => 'flat', 'attendee_price_minor' => 9999]);
            $this->assertSame($expected, $booking->fresh()->price_minor);
            $this->assertSame($expected, (int) $booking->priceLines()->sum('amount_minor'));
        }
    }

    public function test_two_clients_share_a_session_but_are_priced_only_for_their_own_seats(): void
    {
        [, $type] = $this->scheduledType('absolute');
        $start = CarbonImmutable::parse('2026-08-31 09:00:00', 'America/Toronto')->utc();
        $firstHold = app(PublicBookingHoldService::class)->acquire($type, $start, 60, 'America/Toronto', 2);
        $first = app(BookingCreationService::class)->createFromHold($firstHold->token, [
            'first_name' => 'First', 'last_name' => 'Client', 'email' => 'first@example.test',
        ])->booking;
        $slots = app(PublicBookingAvailabilityService::class)->slots($type, $start->startOfDay(), $start->startOfDay()->addDay(), 60, 'America/Toronto', 12);
        $joinable = collect($slots)->first(fn ($slot) => $slot->startsAtUtc->equalTo($start));
        $this->assertNotNull($joinable?->appointment);
        $this->assertSame(18, $joinable->remainingCapacity);

        $secondHold = app(PublicBookingHoldService::class)->acquire($type, $start, 60, 'America/Toronto', 12);
        $second = app(BookingCreationService::class)->createFromHold($secondHold->token, [
            'first_name' => 'Second', 'last_name' => 'Client', 'email' => 'second@example.test',
        ])->booking;
        $this->assertSame($first->appointment_id, $second->appointment_id);
        $this->assertSame(400, $first->price_minor);
        $this->assertSame(1800, $second->price_minor);
        $this->expectException(RuntimeException::class);
        app(PublicBookingHoldService::class)->acquire($type, $start, 60, 'America/Toronto', 7);
    }

    public function test_questionnaire_and_short_notice_fees_apply_after_attendee_base_without_multiplying_fixed_extras(): void
    {
        [, $type] = $this->scheduledType('accumulative');
        $question = $type->questions()->create(['type' => 'checkboxes', 'label' => 'Extras', 'is_active' => true, 'position' => 1]);
        $fixed = $question->options()->create(['label' => 'Print', 'value' => 'print', 'position' => 1, 'is_active' => true, 'pricing_adjustment_type' => 'fixed', 'pricing_amount_minor' => 500]);
        $percentage = $question->options()->create(['label' => 'Premium', 'value' => 'premium', 'position' => 2, 'is_active' => true, 'pricing_adjustment_type' => 'percentage', 'pricing_percentage_bps' => 1000, 'pricing_percentage_basis' => 'base_price']);
        $type->shortNoticeFeeRules()->create(['threshold_value' => 8, 'threshold_unit' => 'day', 'adjustment_type' => 'percentage', 'percentage_bps' => 1000, 'position' => 1, 'is_active' => true]);
        $quote = app(QuestionnairePricingService::class)->quote($type, 60, [$question->uuid => [$fixed->uuid, $percentage->uuid]], CarbonImmutable::parse('2026-08-31 13:00:00', 'UTC'), attendeeCount: 12);
        $this->assertSame(2300, $quote->basePriceMinor);
        $this->assertSame(3333, $quote->totalMinor); // (23 + 5 + 10% of 23) + 10% notice fee.
        $this->assertSame([2000, 300, 500, 230, 303], array_map(fn ($line) => $line->amountMinor, $quote->lines));
    }

    public function test_quote_for_a_different_attendee_count_cannot_be_consumed(): void
    {
        [, $type] = $this->scheduledType('absolute');
        $start = CarbonImmutable::parse('2026-08-31 09:00:00', 'America/Toronto')->utc();
        $hold = app(PublicBookingHoldService::class)->acquire($type, $start, 60, 'America/Toronto', 12);
        $quote = app(QuestionnairePricingService::class)->quote($type, 60, [], $start, attendeeCount: 1);
        try {
            app(BookingCreationService::class)->createFromHold($hold->token, ['first_name' => 'Bad', 'last_name' => 'Quote', 'email' => 'bad@example.test'], questionnaire: new QuestionnaireSubmission([], $quote));
            $this->fail('A price for the wrong attendee count was accepted.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('price has changed', $exception->getMessage());
        }
        $this->assertDatabaseCount('bookings', 0);
        $this->assertTrue($hold->hold->fresh()->isActive());
    }

    private function payload(string $mode): array
    {
        return [
            'name' => 'Photo group '.$mode, 'visibility' => 'public', 'attendance_mode' => 'group', 'capacity' => 20,
            'duration_mode' => 'fixed', 'duration_unit' => 'minute', 'duration_value' => 60,
            'buffer_before_minutes' => 0, 'buffer_after_minutes' => 0, 'is_active' => '1',
            'pricing_mode' => 'per_attendee', 'attendee_pricing_mode' => $mode, 'attendee_price' => '25.00',
            'attendee_price_ranges' => [
                ['min_attendees' => 1, 'max_attendees' => 10, 'unit_price' => '2.00'],
                ['min_attendees' => 11, 'max_attendees' => 20, 'unit_price' => '1.50'],
            ],
        ];
    }

    private function scheduledType(string $mode): array
    {
        $organization = Organization::factory()->create(['timezone' => 'America/Toronto', 'currency' => 'CAD']);
        $type = AppointmentType::create([
            'organization_id' => $organization->getKey(), 'name' => 'Photo group', 'slug' => 'photo-group',
            'visibility' => 'public', 'attendance_mode' => 'group', 'capacity' => 20,
            'duration_mode' => 'fixed', 'duration_unit' => 'minute', 'duration_value' => 60,
            'start_interval_minutes' => 60, 'buffer_before_minutes' => 0, 'buffer_after_minutes' => 0,
            'pricing_mode' => 'per_attendee', 'attendee_pricing_mode' => $mode, 'attendee_price_minor' => 2500,
            'attendee_price_ranges' => [
                ['min_attendees' => 1, 'max_attendees' => 10, 'unit_amount_minor' => 200],
                ['min_attendees' => 11, 'max_attendees' => 20, 'unit_amount_minor' => 150],
            ], 'email_verification_mode' => 'none', 'is_active' => true,
        ]);
        app(AvailabilityScheduleService::class)->save($organization, AvailabilityScope::Organization, $organization, 'America/Toronto', true, [['weekday' => 1, 'start_time' => '09:00', 'end_time' => '12:00']]);

        return [$organization, $type->fresh(['organization', 'resources'])];
    }

    private function ownerContext(): array
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create(['timezone' => 'America/Toronto', 'currency' => 'CAD']);
        OrganizationMembership::create(['organization_id' => $organization->getKey(), 'person_id' => $user->person_id, 'role' => MembershipRole::Owner, 'status' => MembershipStatus::Active]);

        return [$user, $organization];
    }
}
