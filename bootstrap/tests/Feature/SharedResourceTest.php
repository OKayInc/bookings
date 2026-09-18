<?php

namespace Tests\Feature;

use App\Domain\Availability\AvailabilityScheduleService;
use App\Domain\Availability\AvailabilityService;
use App\Domain\Availability\BookingHoldService;
use App\Enums\AvailabilityScope;
use App\Enums\MembershipRole;
use App\Enums\MembershipStatus;
use App\Models\AppointmentType;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\Resource;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class SharedResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_resource_created_without_explicit_default_inherits_database_required_default(): void
    {
        $organization = Organization::factory()->create();

        $resource = Resource::create([
            'organization_id' => $organization->getKey(),
            'type' => 'person',
            'name' => 'Default-required resource',
            'timezone' => 'America/Toronto',
            'is_active' => true,
        ]);

        $this->assertTrue($resource->fresh()->is_required_by_default);
        $this->assertTrue($resource->defaultRequiredForOrganization($organization));
        $this->assertDatabaseHas('organization_resources', [
            'organization_id' => $organization->getKey(),
            'resource_id' => $resource->getKey(),
            'is_required_by_default' => 1,
        ]);
    }

    public function test_owner_can_share_one_resource_with_another_owned_organization(): void
    {
        [$user, $first, $second] = $this->twoOwnedOrganizations();

        $this->actingAs($user)
            ->withSession(['active_organization_uuid' => $first->uuid])
            ->post(route('resources.store'), [
                'name' => 'Shared Studio',
                'type' => 'room',
                'timezone' => 'America/Toronto',
                'default_requirement' => 'required',
                'is_active' => '1',
                'shared_organization_uuids' => [$second->uuid],
            ])
            ->assertRedirect(route('resources.index'));

        $resource = Resource::where('name', 'Shared Studio')->firstOrFail();

        $this->assertTrue(hash_equals($first->getKey(), $resource->organization_id));
        $this->assertTrue($first->resources()->whereKey($resource->getKey())->exists());
        $this->assertTrue($second->resources()->whereKey($resource->getKey())->exists());
        $this->assertSame(2, $resource->organizations()->count());
    }

    public function test_shared_resource_has_independent_availability_per_organization(): void
    {
        [, $first, $second] = $this->twoOwnedOrganizations();
        $resource = $this->sharedResource($first, $second);
        $firstType = $this->appointmentType($first, 'first-session');
        $secondType = $this->appointmentType($second, 'second-session');
        $firstType->resources()->attach($resource->getKey(), ['is_required' => true, 'requirement_mode' => 'required']);
        $secondType->resources()->attach($resource->getKey(), ['is_required' => true, 'requirement_mode' => 'required']);

        $schedules = app(AvailabilityScheduleService::class);
        foreach ([$first, $second] as $organization) {
            $schedules->save($organization, AvailabilityScope::Organization, $organization, 'America/Toronto', true, [
                ['weekday' => 1, 'start_time' => '08:00', 'end_time' => '18:00'],
            ]);
        }
        $schedules->save($first, AvailabilityScope::Resource, $resource, 'America/Toronto', true, [
            ['weekday' => 1, 'start_time' => '09:00', 'end_time' => '12:00'],
        ]);
        $schedules->save($second, AvailabilityScope::Resource, $resource, 'America/Toronto', true, [
            ['weekday' => 1, 'start_time' => '14:00', 'end_time' => '17:00'],
        ]);

        $from = CarbonImmutable::parse('2026-08-24 00:00', 'America/Toronto')->utc();
        $to = CarbonImmutable::parse('2026-08-25 00:00', 'America/Toronto')->utc();

        $firstSlots = app(AvailabilityService::class)->slots($firstType->fresh(['organization', 'resources']), $from, $to, null, 'America/Toronto');
        $secondSlots = app(AvailabilityService::class)->slots($secondType->fresh(['organization', 'resources']), $from, $to, null, 'America/Toronto');

        $this->assertSame(['09:00', '10:00', '11:00'], $this->localTimes($firstSlots));
        $this->assertSame(['14:00', '15:00', '16:00'], $this->localTimes($secondSlots));
    }

    public function test_hold_in_one_organization_blocks_same_shared_resource_in_other_organization(): void
    {
        [, $first, $second] = $this->twoOwnedOrganizations();
        $resource = $this->sharedResource($first, $second);
        $firstType = $this->appointmentType($first, 'first-session');
        $secondType = $this->appointmentType($second, 'second-session');
        $firstType->resources()->attach($resource->getKey(), ['is_required' => true, 'requirement_mode' => 'required']);
        $secondType->resources()->attach($resource->getKey(), ['is_required' => true, 'requirement_mode' => 'required']);

        $schedules = app(AvailabilityScheduleService::class);
        foreach ([$first, $second] as $organization) {
            $schedules->save($organization, AvailabilityScope::Organization, $organization, 'America/Toronto', true, [
                ['weekday' => 1, 'start_time' => '09:00', 'end_time' => '17:00'],
            ]);
            $schedules->save($organization, AvailabilityScope::Resource, $resource, 'America/Toronto', true, [
                ['weekday' => 1, 'start_time' => '09:00', 'end_time' => '17:00'],
            ]);
        }

        $startsAt = CarbonImmutable::parse('2026-08-24 10:00', 'America/Toronto')->utc();
        app(BookingHoldService::class)->acquire($firstType->fresh(['organization', 'resources']), $startsAt, null, 'America/Toronto', 10);

        $this->expectException(RuntimeException::class);
        app(BookingHoldService::class)->acquire($secondType->fresh(['organization', 'resources']), $startsAt, null, 'America/Toronto', 10);
    }

    private function twoOwnedOrganizations(): array
    {
        $user = User::factory()->create();
        $first = Organization::factory()->create(['timezone' => 'America/Toronto']);
        $second = Organization::factory()->create(['timezone' => 'America/Toronto']);

        foreach ([$first, $second] as $organization) {
            OrganizationMembership::create([
                'organization_id' => $organization->getKey(),
                'person_id' => $user->person_id,
                'role' => MembershipRole::Owner,
                'status' => MembershipStatus::Active,
            ]);
        }

        return [$user, $first, $second];
    }

    private function sharedResource(Organization $owner, Organization $sharedWith): Resource
    {
        $resource = Resource::create([
            'organization_id' => $owner->getKey(),
            'type' => 'room',
            'name' => 'Shared room',
            'timezone' => 'America/Toronto',
            'is_active' => true,
            'is_required_by_default' => true,
        ]);
        $resource->organizations()->syncWithoutDetaching([
            $sharedWith->getKey() => ['is_required_by_default' => true],
        ]);

        return $resource;
    }

    private function appointmentType(Organization $organization, string $slug): AppointmentType
    {
        return AppointmentType::create([
            'organization_id' => $organization->getKey(),
            'name' => ucfirst(str_replace('-', ' ', $slug)),
            'slug' => $slug,
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
        ]);
    }

    private function localTimes(array $slots): array
    {
        return array_map(
            fn ($slot) => $slot->startsAtUtc->setTimezone('America/Toronto')->format('H:i'),
            $slots,
        );
    }
}
