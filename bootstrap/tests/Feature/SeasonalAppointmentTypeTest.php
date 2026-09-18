<?php

namespace Tests\Feature;

use App\Domain\Availability\AvailabilityScheduleService;
use App\Domain\Availability\AppointmentTypeSeasonService;
use App\Domain\Bookings\BookingCreationService;
use App\Domain\Bookings\PublicBookingAvailabilityService;
use App\Domain\Bookings\PublicBookingHoldService;
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
use RuntimeException;
use Tests\TestCase;

class SeasonalAppointmentTypeTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_owner_can_configure_a_yearly_booking_season(): void
    {
        [$owner, $organization] = $this->ownerContext();

        $this->actingAs($owner)
            ->withSession(['active_organization_uuid' => $organization->uuid])
            ->post(route('appointment-types.store'), array_merge($this->typeData(), [
                'seasonal_availability_enabled' => '1',
                'season_start_date' => '2026-05-15',
                'season_end_date' => '2026-08-31',
                'season_recurrence' => 'yearly',
            ]))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('appointment-types.index'));

        $type = AppointmentType::query()->firstOrFail();
        $this->assertTrue($type->seasonal_availability_enabled);
        $this->assertSame('2026-05-15', $type->season_start_date->format('Y-m-d'));
        $this->assertSame('2026-08-31', $type->season_end_date->format('Y-m-d'));
        $this->assertSame('yearly', $type->season_recurrence->value);
    }

    public function test_yearly_season_must_be_ordered_and_shorter_than_one_year(): void
    {
        [$owner, $organization] = $this->ownerContext();

        $this->actingAs($owner)
            ->withSession(['active_organization_uuid' => $organization->uuid])
            ->post(route('appointment-types.store'), array_merge($this->typeData(), [
                'seasonal_availability_enabled' => '1',
                'season_start_date' => '2026-11-15',
                'season_end_date' => '2026-02-15',
                'season_recurrence' => 'yearly',
            ]))
            ->assertSessionHasErrors('season_end_date');

        $this->actingAs($owner)
            ->withSession(['active_organization_uuid' => $organization->uuid])
            ->post(route('appointment-types.store'), array_merge($this->typeData(), [
                'seasonal_availability_enabled' => '1',
                'season_start_date' => '2026-05-15',
                'season_end_date' => '2027-05-15',
                'season_recurrence' => 'yearly',
            ]))
            ->assertSessionHasErrors('season_end_date');

        $this->assertDatabaseCount('appointment_types', 0);
    }

    public function test_yearly_and_cross_new_year_seasons_use_the_organization_timezone(): void
    {
        $organization = Organization::factory()->create(['timezone' => 'America/Toronto']);
        $type = $this->type($organization, [
            'seasonal_availability_enabled' => true,
            'season_start_date' => '2026-11-15',
            'season_end_date' => '2027-02-15',
            'season_recurrence' => 'yearly',
        ]);
        $seasons = app(AppointmentTypeSeasonService::class);

        $this->assertTrue($seasons->contains(
            $type,
            CarbonImmutable::parse('2028-01-10 14:00:00', 'UTC'),
            CarbonImmutable::parse('2028-01-10 15:00:00', 'UTC'),
        ));
        $this->assertFalse($seasons->contains(
            $type,
            CarbonImmutable::parse('2027-10-10 14:00:00', 'UTC'),
            CarbonImmutable::parse('2027-10-10 15:00:00', 'UTC'),
        ));

        $type->update([
            'season_start_date' => '2026-05-15',
            'season_end_date' => '2026-08-31',
        ]);
        $type->refresh();
        $this->assertTrue($seasons->contains(
            $type,
            CarbonImmutable::parse('2027-09-01 02:00:00', 'UTC'),
            CarbonImmutable::parse('2027-09-01 04:00:00', 'UTC'),
        ));
        $this->assertFalse($seasons->contains(
            $type,
            CarbonImmutable::parse('2027-09-01 03:00:00', 'UTC'),
            CarbonImmutable::parse('2027-09-01 04:00:01', 'UTC'),
        ));
    }

    public function test_one_time_season_does_not_repeat_and_february_29_clamps_in_non_leap_years(): void
    {
        $organization = Organization::factory()->create(['timezone' => 'America/Toronto']);
        $type = $this->type($organization, [
            'seasonal_availability_enabled' => true,
            'season_start_date' => '2026-05-15',
            'season_end_date' => '2026-05-20',
            'season_recurrence' => 'once',
        ]);
        $seasons = app(AppointmentTypeSeasonService::class);

        $this->assertTrue($seasons->contains(
            $type,
            CarbonImmutable::parse('2026-05-18 14:00:00', 'UTC'),
            CarbonImmutable::parse('2026-05-18 15:00:00', 'UTC'),
        ));
        $this->assertFalse($seasons->contains(
            $type,
            CarbonImmutable::parse('2027-05-18 14:00:00', 'UTC'),
            CarbonImmutable::parse('2027-05-18 15:00:00', 'UTC'),
        ));

        $type->update([
            'season_start_date' => '2024-02-29',
            'season_end_date' => '2024-03-01',
            'season_recurrence' => 'yearly',
        ]);
        $type->refresh();
        $this->assertTrue($seasons->contains(
            $type,
            CarbonImmutable::parse('2025-02-28 15:00:00', 'UTC'),
            CarbonImmutable::parse('2025-02-28 16:00:00', 'UTC'),
        ));
    }

    public function test_off_season_type_is_hidden_from_public_list_but_direct_page_remains_available(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-01 16:00:00', 'UTC'));
        $organization = Organization::factory()->create([
            'name' => 'Flower Farm',
            'slug' => 'flower-farm',
            'timezone' => 'America/Toronto',
        ]);
        $type = $this->type($organization, [
            'name' => 'Summer Flower at the farm',
            'slug' => 'summer-flower',
            'seasonal_availability_enabled' => true,
            'season_start_date' => '2026-05-15',
            'season_end_date' => '2026-08-31',
            'season_recurrence' => 'yearly',
        ]);

        $this->get(route('public.appointment-types.index', $organization->slug))
            ->assertOk()
            ->assertDontSee('Summer Flower at the farm');
        $this->get(route('public.appointment-types.show', [$organization->slug, $type->slug]))
            ->assertOk()
            ->assertSee('Summer Flower at the farm')
            ->assertSee('May 15 – Aug 31 every year');

        CarbonImmutable::setTestNow(CarbonImmutable::parse('2027-06-01 16:00:00', 'UTC'));
        $this->get(route('public.appointment-types.index', $organization->slug))
            ->assertOk()
            ->assertSee('Summer Flower at the farm');
    }

    public function test_slots_are_empty_outside_season_and_cannot_cross_its_end(): void
    {
        $organization = Organization::factory()->create(['timezone' => 'America/Toronto']);
        $type = $this->type($organization, [
            'duration_value' => 120,
            'seasonal_availability_enabled' => true,
            'season_start_date' => '2026-06-01',
            'season_end_date' => '2026-06-30',
            'season_recurrence' => 'yearly',
        ]);
        $this->scheduleEveryDay($organization);
        $availability = app(PublicBookingAvailabilityService::class);

        $outsideStart = CarbonImmutable::parse('2027-07-01 00:00:00', 'America/Toronto')->utc();
        $this->assertSame([], $availability->slots(
            $type,
            $outsideStart,
            $outsideStart->addDay(),
            120,
            'America/Toronto',
            1,
            false,
        ));

        $lastDay = CarbonImmutable::parse('2027-06-30 00:00:00', 'America/Toronto')->utc();
        $slots = $availability->slots(
            $type,
            $lastDay,
            $lastDay->addDay(),
            120,
            'America/Toronto',
            1,
            false,
        );
        $localStarts = collect($slots)
            ->map(fn ($slot): string => $slot->startsAtUtc->setTimezone('America/Toronto')->format('H:i'))
            ->values()
            ->all();

        $this->assertContains('22:00', $localStarts);
        $this->assertNotContains('23:00', $localStarts);
    }

    public function test_existing_group_session_is_not_joinable_after_its_season_changes(): void
    {
        $organization = Organization::factory()->create(['timezone' => 'America/Toronto']);
        $type = $this->type($organization, [
            'attendance_mode' => 'group',
            'capacity' => 5,
            'seasonal_availability_enabled' => true,
            'season_start_date' => '2026-06-01',
            'season_end_date' => '2026-08-31',
            'season_recurrence' => 'yearly',
            'email_verification_mode' => 'none',
        ]);
        $this->scheduleEveryDay($organization);
        $start = CarbonImmutable::parse('2027-07-10 09:00:00', 'America/Toronto')->utc();
        $lease = app(PublicBookingHoldService::class)->acquire(
            $type,
            $start,
            60,
            'America/Toronto',
            1,
            null,
            false,
        );
        app(BookingCreationService::class)->createFromHold($lease->token, [
            'first_name' => 'Existing',
            'last_name' => 'Client',
            'email' => 'existing.group@example.test',
        ]);

        $type->update([
            'season_start_date' => '2026-09-01',
            'season_end_date' => '2026-09-30',
        ]);
        $type = $type->fresh(['organization', 'resources']);
        $dayStart = CarbonImmutable::parse('2027-07-10 00:00:00', 'America/Toronto')->utc();
        $this->assertSame([], app(PublicBookingAvailabilityService::class)->slots(
            $type,
            $dayStart,
            $dayStart->addDay(),
            60,
            'America/Toronto',
            1,
            false,
        ));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('not offered during the selected dates');
        app(PublicBookingHoldService::class)->acquire(
            $type,
            $start,
            60,
            'America/Toronto',
            1,
            null,
            false,
        );
    }

    public function test_season_changed_after_hold_creation_blocks_final_booking(): void
    {
        $organization = Organization::factory()->create(['timezone' => 'America/Toronto']);
        $type = $this->type($organization, [
            'seasonal_availability_enabled' => true,
            'season_start_date' => '2026-06-01',
            'season_end_date' => '2026-08-31',
            'season_recurrence' => 'yearly',
            'email_verification_mode' => 'none',
        ]);
        $this->scheduleEveryDay($organization);
        $start = CarbonImmutable::parse('2027-07-10 09:00:00', 'America/Toronto')->utc();
        $lease = app(PublicBookingHoldService::class)->acquire(
            $type,
            $start,
            60,
            'America/Toronto',
            1,
            null,
            false,
        );

        $type->update([
            'season_start_date' => '2026-09-01',
            'season_end_date' => '2026-09-30',
        ]);

        try {
            app(BookingCreationService::class)->createFromHold($lease->token, [
                'first_name' => 'Season',
                'last_name' => 'Client',
                'email' => 'season@example.test',
            ]);
            $this->fail('A hold was consumed after its appointment type moved out of season.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('no longer offered', $exception->getMessage());
        }

        $this->assertSame(0, Booking::query()->count());
        $this->assertTrue($lease->hold->fresh()->isActive());
    }

    /** @return array<string, mixed> */
    private function typeData(): array
    {
        return [
            'name' => 'Summer Flower at the farm',
            'visibility' => 'public',
            'attendance_mode' => 'single',
            'duration_mode' => 'fixed',
            'duration_unit' => 'minute',
            'duration_value' => 60,
            'buffer_before_minutes' => 0,
            'buffer_after_minutes' => 0,
            'pricing_mode' => 'free',
            'is_active' => '1',
        ];
    }

    private function type(Organization $organization, array $overrides = []): AppointmentType
    {
        return AppointmentType::create(array_merge([
            'organization_id' => $organization->getKey(),
            'name' => 'Seasonal appointment',
            'slug' => 'seasonal-'.bin2hex(random_bytes(3)),
            'visibility' => 'public',
            'attendance_mode' => 'single',
            'capacity' => 1,
            'duration_mode' => 'fixed',
            'duration_unit' => 'minute',
            'duration_value' => 60,
            'start_interval_minutes' => 60,
            'buffer_before_minutes' => 0,
            'buffer_after_minutes' => 0,
            'pricing_mode' => 'free',
            'is_active' => true,
        ], $overrides));
    }

    private function scheduleEveryDay(Organization $organization): void
    {
        app(AvailabilityScheduleService::class)->save(
            $organization,
            AvailabilityScope::Organization,
            $organization,
            'America/Toronto',
            true,
            array_map(fn (int $weekday): array => [
                'weekday' => $weekday,
                'start_time' => '00:00',
                'end_time' => '23:59',
            ], range(0, 6)),
        );
    }

    /** @return array{User, Organization} */
    private function ownerContext(): array
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create(['timezone' => 'America/Toronto']);
        OrganizationMembership::create([
            'organization_id' => $organization->getKey(),
            'person_id' => $user->person_id,
            'role' => MembershipRole::Owner,
            'status' => MembershipStatus::Active,
        ]);

        return [$user, $organization];
    }
}
