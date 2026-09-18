<?php

namespace Tests\Feature;

use App\Domain\Availability\AvailabilityScheduleService;
use App\Domain\Availability\AvailabilityService;
use App\Domain\Availability\BookingHoldService;
use App\Domain\Resources\ResourceRequirementService;
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
use Tests\TestCase;

class ResourceRequirementTest extends TestCase
{
    use RefreshDatabase;

    public function test_organization_default_can_be_optional_and_appointment_type_can_override_it(): void
    {
        [$user, $organization] = $this->ownerContext();

        $this->actingAs($user)
            ->withSession(['active_organization_uuid' => $organization->uuid])
            ->post(route('resources.store'), [
                'name' => 'Second camera operator',
                'type' => 'person',
                'timezone' => 'America/Toronto',
                'default_requirement' => 'optional',
                'is_active' => '1',
            ])
            ->assertRedirect(route('resources.index'));

        $resource = Resource::where('name', 'Second camera operator')->firstOrFail();
        $this->assertFalse($resource->is_required_by_default);

        $this->actingAs($user)
            ->withSession(['active_organization_uuid' => $organization->uuid])
            ->post(route('appointment-types.store'), $this->validTypePayload() + [
                'resource_uuids' => [$resource->uuid],
                'resource_requirement_modes' => [$resource->uuid => 'required'],
            ])
            ->assertRedirect(route('appointment-types.index'));

        $type = AppointmentType::where('name', 'Resource Test Session')->firstOrFail();
        $assigned = $type->resources()->firstOrFail();
        $this->assertSame('required', $assigned->pivot->requirement_mode);
        $this->assertTrue((bool) $assigned->pivot->is_required);
        $this->assertTrue(app(ResourceRequirementService::class)->isRequired($assigned));
    }

    public function test_unavailable_optional_resource_does_not_remove_slot_or_enter_hold(): void
    {
        [$type, $resource] = $this->configuredType(defaultRequired: false, mode: 'inherit');
        $organization = $type->organization;
        $schedules = app(AvailabilityScheduleService::class);

        $schedules->save($organization, AvailabilityScope::Organization, $organization, 'America/Toronto', true, [
            ['weekday' => 1, 'start_time' => '09:00', 'end_time' => '12:00'],
        ]);
        $schedules->save($organization, AvailabilityScope::Resource, $resource, 'America/Toronto', false, []);

        $start = CarbonImmutable::parse('2026-08-24 00:00', 'America/Toronto')->utc();
        $end = CarbonImmutable::parse('2026-08-25 00:00', 'America/Toronto')->utc();
        $slots = app(AvailabilityService::class)->slots($type->fresh(['organization', 'resources']), $start, $end, null, 'America/Toronto');

        $this->assertSame(['09:00', '10:00', '11:00'], array_map(
            fn ($slot) => $slot->startsAtUtc->setTimezone('America/Toronto')->format('H:i'),
            $slots,
        ));

        $lease = app(BookingHoldService::class)->acquire(
            $type->fresh(['organization', 'resources']),
            CarbonImmutable::parse('2026-08-24 09:00', 'America/Toronto')->utc(),
            null,
            'America/Toronto',
            10,
        );

        $this->assertCount(0, $lease->hold->resources);
    }

    public function test_available_optional_resource_is_reserved_but_marked_optional(): void
    {
        [$type, $resource] = $this->configuredType(defaultRequired: false, mode: 'inherit');
        $organization = $type->organization;
        app(AvailabilityScheduleService::class)->save($organization, AvailabilityScope::Organization, $organization, 'America/Toronto', true, [
            ['weekday' => 1, 'start_time' => '09:00', 'end_time' => '12:00'],
        ]);

        $lease = app(BookingHoldService::class)->acquire(
            $type->fresh(['organization', 'resources']),
            CarbonImmutable::parse('2026-08-24 09:00', 'America/Toronto')->utc(),
            null,
            'America/Toronto',
            10,
        );

        $held = $lease->hold->resources->firstOrFail();
        $this->assertTrue(hash_equals($resource->getKey(), $held->getKey()));
        $this->assertFalse((bool) $held->pivot->is_required);
    }

    public function test_unavailable_required_resource_blocks_slots(): void
    {
        [$type, $resource] = $this->configuredType(defaultRequired: false, mode: 'required');
        $organization = $type->organization;
        $schedules = app(AvailabilityScheduleService::class);
        $schedules->save($organization, AvailabilityScope::Organization, $organization, 'America/Toronto', true, [
            ['weekday' => 1, 'start_time' => '09:00', 'end_time' => '12:00'],
        ]);
        $schedules->save($organization, AvailabilityScope::Resource, $resource, 'America/Toronto', false, []);

        $start = CarbonImmutable::parse('2026-08-24 00:00', 'America/Toronto')->utc();
        $end = CarbonImmutable::parse('2026-08-25 00:00', 'America/Toronto')->utc();

        $this->assertSame([], app(AvailabilityService::class)->slots(
            $type->fresh(['organization', 'resources']),
            $start,
            $end,
            null,
            'America/Toronto',
        ));
    }

    private function configuredType(bool $defaultRequired, string $mode): array
    {
        $organization = Organization::factory()->create(['timezone' => 'America/Toronto']);
        $type = AppointmentType::create([
            'organization_id' => $organization->getKey(),
            'name' => 'Requirement Session',
            'slug' => 'requirement-session',
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
        $resource = Resource::create([
            'organization_id' => $organization->getKey(),
            'type' => 'person',
            'name' => 'Resource',
            'timezone' => 'America/Toronto',
            'is_active' => true,
            'is_required_by_default' => $defaultRequired,
        ]);
        $effective = $mode === 'required' || ($mode === 'inherit' && $defaultRequired);
        $type->resources()->attach($resource->getKey(), [
            'is_required' => $effective,
            'requirement_mode' => $mode,
        ]);

        return [$type->fresh(['organization', 'resources']), $resource];
    }

    private function validTypePayload(): array
    {
        return [
            'name' => 'Resource Test Session',
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
}
