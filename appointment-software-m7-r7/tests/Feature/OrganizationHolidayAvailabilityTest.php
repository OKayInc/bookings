<?php

namespace Tests\Feature;

use App\Domain\Availability\AvailabilityScheduleService;
use App\Domain\Availability\AvailabilityService;
use App\Domain\Availability\BookingHoldService;
use App\Domain\Availability\OrganizationHolidayService;
use App\Domain\Bookings\BookingCreationService;
use App\Domain\Bookings\PublicBookingAvailabilityService;
use App\Domain\Bookings\PublicBookingHoldService;
use App\Enums\AvailabilityExceptionMode;
use App\Enums\AvailabilityScope;
use App\Enums\HolidayRuleType;
use App\Enums\MembershipRole;
use App\Enums\MembershipStatus;
use App\Models\AppointmentType;
use App\Models\Organization;
use App\Models\OrganizationHoliday;
use App\Models\OrganizationMembership;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class OrganizationHolidayAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_fixed_annual_holiday_blocks_all_organization_slots(): void
    {
        [$organization, $type] = $this->typeWithSchedule(5);
        OrganizationHoliday::create([
            'organization_id' => $organization->getKey(),
            'name' => 'Christmas Day',
            'rule_type' => HolidayRuleType::FixedAnnual,
            'month' => 12,
            'day' => 25,
            'is_active' => true,
        ]);

        $slots = $this->slotsForDate($type, '2026-12-25');

        $this->assertSame([], $slots);
    }

    public function test_inactive_holiday_does_not_change_normal_availability(): void
    {
        [$organization, $type] = $this->typeWithSchedule(5);
        OrganizationHoliday::create([
            'organization_id' => $organization->getKey(),
            'name' => 'Christmas Day',
            'rule_type' => HolidayRuleType::FixedAnnual,
            'month' => 12,
            'day' => 25,
            'is_active' => false,
        ]);

        $slots = $this->slotsForDate($type, '2026-12-25');

        $this->assertSame(['09:00', '10:00', '11:00'], $slots);
    }

    public function test_easter_rule_calculates_different_dates_each_year(): void
    {
        [$organization, $type] = $this->typeWithSchedule(0);
        $holiday = OrganizationHoliday::create([
            'organization_id' => $organization->getKey(),
            'name' => 'Easter Sunday',
            'rule_type' => HolidayRuleType::EasterRelative,
            'easter_offset_days' => 0,
            'is_active' => true,
        ]);
        $service = app(OrganizationHolidayService::class);

        $this->assertSame('2026-04-05', $service->dateForYear($holiday, 2026, 'America/Toronto')?->format('Y-m-d'));
        $this->assertSame('2027-03-28', $service->dateForYear($holiday, 2027, 'America/Toronto')?->format('Y-m-d'));
        $this->assertSame([], $this->slotsForDate($type, '2026-04-05'));
        $organization->unsetRelation('holidays');
        $type->organization->unsetRelation('holidays');
        $this->assertSame([], $this->slotsForDate($type, '2027-03-28'));
    }

    public function test_holiday_overrides_extra_availability_exception(): void
    {
        [$organization, $type, $schedule] = $this->typeWithSchedule(1);
        $schedule->exceptions()->create([
            'starts_at_utc' => CarbonImmutable::parse('2026-04-05 09:00', 'America/Toronto')->utc(),
            'ends_at_utc' => CarbonImmutable::parse('2026-04-05 12:00', 'America/Toronto')->utc(),
            'mode' => AvailabilityExceptionMode::Available,
            'timezone' => 'America/Toronto',
            'reason' => 'Special Sunday opening',
        ]);
        OrganizationHoliday::create([
            'organization_id' => $organization->getKey(),
            'name' => 'Easter Sunday',
            'rule_type' => HolidayRuleType::EasterRelative,
            'easter_offset_days' => 0,
            'is_active' => true,
        ]);

        $this->assertSame([], $this->slotsForDate($type, '2026-04-05'));
    }

    public function test_booking_hold_cannot_bypass_a_holiday_closure(): void
    {
        [$organization, $type] = $this->typeWithSchedule(5);
        OrganizationHoliday::create([
            'organization_id' => $organization->getKey(),
            'name' => 'Christmas Day',
            'rule_type' => HolidayRuleType::FixedAnnual,
            'month' => 12,
            'day' => 25,
            'is_active' => true,
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('no longer available');

        app(BookingHoldService::class)->acquire(
            $type,
            CarbonImmutable::parse('2026-12-25 09:00', 'America/Toronto')->utc(),
            null,
            'America/Toronto',
        );
    }

    public function test_hold_created_before_closure_cannot_be_consumed_after_holiday_is_enabled(): void
    {
        [$organization, $type] = $this->typeWithSchedule(5);
        $start = CarbonImmutable::parse('2026-12-25 09:00', 'America/Toronto')->utc();
        $lease = app(PublicBookingHoldService::class)->acquire(
            $type,
            $start,
            60,
            'America/Toronto',
            1,
            null,
            false,
        );
        OrganizationHoliday::create([
            'organization_id' => $organization->getKey(),
            'name' => 'Christmas Day',
            'rule_type' => HolidayRuleType::FixedAnnual,
            'month' => 12,
            'day' => 25,
            'is_active' => true,
        ]);

        try {
            app(BookingCreationService::class)->createFromHold($lease->token, [
                'first_name' => 'Holiday',
                'last_name' => 'Client',
                'email' => 'holiday.client@example.test',
            ]);
            $this->fail('A stale hold was consumed after its date became a holiday closure.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('now closed', $exception->getMessage());
        }

        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_existing_group_appointment_is_not_joinable_after_its_date_is_closed(): void
    {
        [$organization, $type] = $this->typeWithSchedule(5);
        $type->update(['attendance_mode' => 'group', 'capacity' => 5]);
        $type = $type->fresh(['organization', 'resources']);
        $start = CarbonImmutable::parse('2026-12-25 09:00', 'America/Toronto')->utc();
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
            'email' => 'existing.client@example.test',
        ]);
        OrganizationHoliday::create([
            'organization_id' => $organization->getKey(),
            'name' => 'Christmas Day',
            'rule_type' => HolidayRuleType::FixedAnnual,
            'month' => 12,
            'day' => 25,
            'is_active' => true,
        ]);

        $slots = app(PublicBookingAvailabilityService::class)->slots(
            $type->fresh(['organization', 'resources']),
            CarbonImmutable::parse('2026-12-25 00:00', 'America/Toronto')->utc(),
            CarbonImmutable::parse('2026-12-26 00:00', 'America/Toronto')->utc(),
            60,
            'America/Toronto',
            1,
            false,
        );
        $this->assertSame([], $slots);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('organization is closed');
        app(PublicBookingHoldService::class)->acquire(
            $type->fresh(['organization', 'resources']),
            $start,
            60,
            'America/Toronto',
            1,
            null,
            false,
        );
    }

    public function test_nth_weekday_rule_calculates_canadian_thanksgiving(): void
    {
        [$organization] = $this->typeWithSchedule(1);
        $holiday = OrganizationHoliday::create([
            'organization_id' => $organization->getKey(),
            'name' => 'Thanksgiving',
            'rule_type' => HolidayRuleType::NthWeekday,
            'month' => 10,
            'weekday' => 1,
            'occurrence' => 2,
            'is_active' => true,
        ]);

        $this->assertSame(
            '2026-10-12',
            app(OrganizationHolidayService::class)
                ->dateForYear($holiday, 2026, 'America/Toronto')?->format('Y-m-d'),
        );
    }

    public function test_holiday_is_isolated_to_its_organization(): void
    {
        [$organizationA, $typeA] = $this->typeWithSchedule(5, 'alpha');
        [, $typeB] = $this->typeWithSchedule(5, 'beta');
        OrganizationHoliday::create([
            'organization_id' => $organizationA->getKey(),
            'name' => 'Christmas Day',
            'rule_type' => HolidayRuleType::FixedAnnual,
            'month' => 12,
            'day' => 25,
            'is_active' => true,
        ]);

        $this->assertSame([], $this->slotsForDate($typeA, '2026-12-25'));
        $this->assertSame(['09:00', '10:00', '11:00'], $this->slotsForDate($typeB, '2026-12-25'));
    }

    public function test_manager_can_add_common_holiday_preset(): void
    {
        [$organization] = $this->typeWithSchedule(1);
        $manager = User::factory()->create();
        OrganizationMembership::create([
            'organization_id' => $organization->getKey(),
            'person_id' => $manager->person_id,
            'role' => MembershipRole::Manager,
            'status' => MembershipStatus::Active,
        ]);

        $this->actingAs($manager)
            ->withSession(['active_organization_uuid' => $organization->uuid])
            ->post(route('availability.holidays.store'), ['preset_key' => 'christmas_day'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $holiday = $organization->holidays()->firstOrFail();
        $this->assertSame(HolidayRuleType::FixedAnnual, $holiday->rule_type);
        $this->assertSame(12, $holiday->month);
        $this->assertSame(25, $holiday->day);
        $this->assertTrue($holiday->is_active);
    }

    public function test_employee_cannot_manage_holiday_closures(): void
    {
        [$organization] = $this->typeWithSchedule(1);
        $employee = User::factory()->create();
        OrganizationMembership::create([
            'organization_id' => $organization->getKey(),
            'person_id' => $employee->person_id,
            'role' => MembershipRole::Employee,
            'status' => MembershipStatus::Active,
        ]);

        $this->actingAs($employee)
            ->withSession(['active_organization_uuid' => $organization->uuid])
            ->get(route('availability.holidays.index'))
            ->assertForbidden();

        $this->actingAs($employee)
            ->post(route('availability.holidays.store'), ['preset_key' => 'christmas_day'])
            ->assertForbidden();

        $this->assertSame(0, $organization->holidays()->count());
    }

    private function typeWithSchedule(int $weekday, ?string $suffix = null): array
    {
        $suffix ??= uniqid();
        $organization = Organization::factory()->create([
            'name' => 'Holiday Organization '.$suffix,
            'slug' => 'holiday-organization-'.$suffix,
            'timezone' => 'America/Toronto',
        ]);
        $type = AppointmentType::create([
            'organization_id' => $organization->getKey(),
            'name' => 'Holiday Session '.$suffix,
            'slug' => 'holiday-session-'.$suffix,
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
            'email_verification_mode' => 'none',
            'is_active' => true,
        ]);
        $schedule = app(AvailabilityScheduleService::class)->save(
            $organization,
            AvailabilityScope::Organization,
            $organization,
            'America/Toronto',
            true,
            [['weekday' => $weekday, 'start_time' => '09:00', 'end_time' => '12:00']],
        );

        return [$organization, $type->fresh(['organization', 'resources']), $schedule];
    }

    /** @return list<string> */
    private function slotsForDate(AppointmentType $type, string $date): array
    {
        $start = CarbonImmutable::parse($date.' 00:00', 'America/Toronto')->utc();
        $end = CarbonImmutable::parse($date.' 00:00', 'America/Toronto')->addDay()->utc();
        $slots = app(AvailabilityService::class)->slots(
            $type->fresh(['organization', 'resources']),
            $start,
            $end,
            null,
            'America/Toronto',
        );

        return array_map(
            fn ($slot) => $slot->startsAtUtc->setTimezone('America/Toronto')->format('H:i'),
            $slots,
        );
    }
}
