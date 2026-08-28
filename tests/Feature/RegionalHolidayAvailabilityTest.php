<?php

namespace Tests\Feature;

use App\Domain\Availability\AvailabilityScheduleService;
use App\Domain\Availability\AvailabilityService;
use App\Domain\Availability\BookingHoldService;
use App\Domain\Availability\HolidayRegionCatalog;
use App\Domain\Availability\PublicHolidayCalendar;
use App\Domain\Bookings\BookingCreationService;
use App\Domain\Bookings\PublicBookingHoldService;
use App\Enums\AvailabilityScope;
use App\Enums\HolidayRuleType;
use App\Enums\MembershipRole;
use App\Enums\MembershipStatus;
use App\Models\AppointmentType;
use App\Models\Organization;
use App\Models\OrganizationHoliday;
use App\Models\OrganizationMembership;
use App\Models\Resource;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use RuntimeException;
use Tests\TestCase;

class RegionalHolidayAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_timezone_suggests_a_country_or_subdivision_without_geo_ip(): void
    {
        $regions = app(HolidayRegionCatalog::class);

        $this->assertSame('CA-ON', $regions->detect('America/Toronto'));
        $this->assertSame('MX', $regions->detect('America/Mexico_City'));
        $this->assertArrayHasKey('US', $regions->options());
        $this->assertArrayHasKey('GB-SCT', $regions->options());
    }

    public function test_resource_form_saves_enforcement_on_the_organization_relationship(): void
    {
        [$user, $organization] = $this->ownerContext();

        $this->actingAs($user)
            ->withSession(['active_organization_uuid' => $organization->uuid])
            ->post(route('resources.store'), [
                'name' => 'Mexico City person',
                'type' => 'person',
                'timezone' => 'America/Mexico_City',
                'default_requirement' => 'required',
                'enforce_holidays' => '1',
                'holiday_region' => 'MX',
                'is_active' => '1',
            ])
            ->assertRedirect(route('resources.index'))
            ->assertSessionHasNoErrors();

        $resource = Resource::where('name', 'Mexico City person')->firstOrFail();
        $this->assertDatabaseHas('organization_resources', [
            'organization_id' => $organization->getKey(),
            'resource_id' => $resource->getKey(),
            'enforce_holidays' => 1,
            'holiday_region' => 'MX',
        ]);
    }

    public function test_resource_create_and_edit_forms_receive_the_active_organization(): void
    {
        [$user, $organization] = $this->ownerContext(['name' => 'Canadian Studio']);
        $resource = Resource::create([
            'organization_id' => $organization->getKey(),
            'type' => 'person',
            'name' => 'Existing photographer',
            'timezone' => 'America/Toronto',
            'is_active' => true,
            'is_required_by_default' => true,
        ]);
        $resource->organizations()->updateExistingPivot($organization->getKey(), [
            'enforce_holidays' => true,
            'holiday_region' => 'CA-ON',
        ]);

        $this->actingAs($user)
            ->withSession(['active_organization_uuid' => $organization->uuid])
            ->get(route('resources.create'))
            ->assertOk()
            ->assertSee('This setting belongs to Canadian Studio');

        $this->actingAs($user)
            ->withSession(['active_organization_uuid' => $organization->uuid])
            ->get(route('resources.edit', $resource))
            ->assertOk()
            ->assertSee('This setting belongs to Canadian Studio')
            ->assertSee('value="CA-ON" selected', false);
    }

    public function test_selected_regional_holiday_is_not_listed_or_created_twice(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-28 12:00:00', 'UTC'));
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-28 12:00:00', 'UTC'));
        [$user, $organization] = $this->ownerContext(['holiday_region' => 'MX']);
        $calendar = app(PublicHolidayCalendar::class);
        $preset = collect($calendar->available('MX', 2026, 3))->firstOrFail();

        $this->actingAs($user)
            ->withSession(['active_organization_uuid' => $organization->uuid])
            ->post(route('availability.holidays.store'), [
                'region_code' => 'MX',
                'provider_holiday_key' => $preset['key'],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->actingAs($user)
            ->withSession(['active_organization_uuid' => $organization->uuid])
            ->get(route('availability.holidays.index'))
            ->assertOk()
            ->assertDontSee('value="'.$preset['key'].'"', false);

        $this->actingAs($user)
            ->withSession(['active_organization_uuid' => $organization->uuid])
            ->post(route('availability.holidays.store'), [
                'region_code' => 'MX',
                'provider_holiday_key' => $preset['key'],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(1, $organization->holidays()->count());
    }

    public function test_legacy_rule_suppresses_semantically_identical_regional_holiday(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-28 12:00:00', 'UTC'));
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-28 12:00:00', 'UTC'));
        [$user, $organization] = $this->ownerContext(['holiday_region' => 'MX']);
        $calendar = app(PublicHolidayCalendar::class);
        $christmas = collect($calendar->available('MX', 2026, 3))->first(
            fn (array $holiday): bool => ($holiday['dates'][2026] ?? null) === '2026-12-25',
        );
        $this->assertNotNull($christmas);

        $legacy = OrganizationHoliday::create([
            'organization_id' => $organization->getKey(),
            'name' => 'Christmas Day',
            'rule_type' => HolidayRuleType::FixedAnnual,
            'month' => 12,
            'day' => 25,
            'is_active' => false,
        ]);

        $this->actingAs($user)
            ->withSession(['active_organization_uuid' => $organization->uuid])
            ->get(route('availability.holidays.index'))
            ->assertOk()
            ->assertDontSee('value="'.$christmas['key'].'"', false);

        $this->actingAs($user)
            ->withSession(['active_organization_uuid' => $organization->uuid])
            ->post(route('availability.holidays.store'), [
                'region_code' => 'MX',
                'provider_holiday_key' => $christmas['key'],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(1, $organization->holidays()->count());
        $this->assertTrue($legacy->fresh()->is_active);
    }

    public function test_required_canadian_and_mexican_resources_enforce_the_union_of_holidays(): void
    {
        [, $organization, $type] = $this->scheduledType();
        $this->resource($organization, $type, 'Canadian person', 'America/Toronto', 'CA-ON', true);
        $this->resource($organization, $type, 'Mexican person', 'America/Mexico_City', 'MX', true);

        $this->assertSame([], $this->localSlots($type, '2026-07-01'));
        $this->assertSame([], $this->localSlots($type, '2026-09-16'));
        $this->assertSame(['09:00', '10:00', '11:00'], $this->localSlots($type, '2026-08-26'));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('no longer available');
        app(BookingHoldService::class)->acquire(
            $type->fresh(['organization', 'resources']),
            CarbonImmutable::parse('2026-09-16 09:00', 'America/Toronto')->utc(),
            null,
            'America/Toronto',
        );
    }

    public function test_optional_resource_holiday_does_not_remove_the_slot_but_resource_is_not_reserved(): void
    {
        [, $organization, $type] = $this->scheduledType();
        $resource = $this->resource($organization, $type, 'Optional Mexican person', 'America/Mexico_City', 'MX', false);

        $this->assertSame(['09:00', '10:00', '11:00'], $this->localSlots($type, '2026-09-16'));

        $lease = app(BookingHoldService::class)->acquire(
            $type->fresh(['organization', 'resources']),
            CarbonImmutable::parse('2026-09-16 09:00', 'America/Toronto')->utc(),
            null,
            'America/Toronto',
        );

        $this->assertFalse($lease->hold->resources->contains(fn (Resource $held): bool => hash_equals($held->getKey(), $resource->getKey())));
    }

    public function test_resource_holiday_enabled_after_hold_creation_blocks_final_booking(): void
    {
        [, $organization, $type] = $this->scheduledType();
        $resource = $this->resource($organization, $type, 'Mexico person', 'America/Mexico_City', 'MX', true);
        $resource->organizations()->updateExistingPivot($organization->getKey(), ['enforce_holidays' => false]);
        $start = CarbonImmutable::parse('2026-09-16 09:00', 'America/Toronto')->utc();

        $lease = app(PublicBookingHoldService::class)->acquire(
            $type->fresh(['organization', 'resources']),
            $start,
            60,
            'America/Toronto',
            1,
            null,
            false,
        );
        $resource->organizations()->updateExistingPivot($organization->getKey(), ['enforce_holidays' => true]);

        try {
            app(BookingCreationService::class)->createFromHold($lease->token, [
                'first_name' => 'Holiday',
                'last_name' => 'Client',
                'email' => 'resource.holiday@example.test',
            ]);
            $this->fail('A hold was consumed after its required resource holiday was enabled.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('required resource is now closed', $exception->getMessage());
        }

        $this->assertDatabaseCount('bookings', 0);
    }

    private function ownerContext(array $organizationAttributes = []): array
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create(array_merge([
            'timezone' => 'America/Toronto',
        ], $organizationAttributes));
        OrganizationMembership::create([
            'organization_id' => $organization->getKey(),
            'person_id' => $user->person_id,
            'role' => MembershipRole::Owner,
            'status' => MembershipStatus::Active,
        ]);

        return [$user, $organization];
    }

    private function scheduledType(): array
    {
        [$user, $organization] = $this->ownerContext();
        $type = AppointmentType::create([
            'organization_id' => $organization->getKey(),
            'name' => 'Regional holiday session',
            'slug' => 'regional-holiday-session',
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
        app(AvailabilityScheduleService::class)->save(
            $organization,
            AvailabilityScope::Organization,
            $organization,
            'America/Toronto',
            true,
            [['weekday' => 3, 'start_time' => '09:00', 'end_time' => '12:00']],
        );

        return [$user, $organization, $type->fresh(['organization', 'resources'])];
    }

    private function resource(
        Organization $organization,
        AppointmentType $type,
        string $name,
        string $timezone,
        string $region,
        bool $required,
    ): Resource {
        $resource = Resource::create([
            'organization_id' => $organization->getKey(),
            'type' => 'person',
            'name' => $name,
            'timezone' => $timezone,
            'is_active' => true,
            'is_required_by_default' => $required,
        ]);
        $resource->organizations()->updateExistingPivot($organization->getKey(), [
            'is_required_by_default' => $required,
            'enforce_holidays' => true,
            'holiday_region' => $region,
        ]);
        $type->resources()->attach($resource->getKey(), [
            'is_required' => $required,
            'requirement_mode' => $required ? 'required' : 'optional',
        ]);

        return $resource;
    }

    /** @return list<string> */
    private function localSlots(AppointmentType $type, string $date): array
    {
        $start = CarbonImmutable::parse($date.' 00:00', 'America/Toronto')->utc();
        $end = CarbonImmutable::parse($date.' 00:00', 'America/Toronto')->addDay()->utc();

        return array_map(
            fn ($slot): string => $slot->startsAtUtc->setTimezone('America/Toronto')->format('H:i'),
            app(AvailabilityService::class)->slots(
                $type->fresh(['organization', 'resources']),
                $start,
                $end,
                null,
                'America/Toronto',
            ),
        );
    }
}
