<?php

namespace Tests\Feature;

use App\Enums\AvailabilityScope;
use App\Enums\MembershipRole;
use App\Enums\MembershipStatus;
use App\Models\AvailabilitySchedule;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\Resource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AvailabilityConfigurationTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_save_organization_weekly_hours(): void
    {
        [$user, $organization] = $this->ownerContext();

        $response = $this->actingAs($user)
            ->withSession(['active_organization_uuid' => $organization->uuid])
            ->put(route('availability.organization.update'), [
                'timezone' => 'America/Toronto',
                'is_active' => '1',
                'rules' => [
                    ['weekday' => 1, 'start_time' => '09:00', 'end_time' => '12:00'],
                    ['weekday' => 1, 'start_time' => '13:00', 'end_time' => '17:00'],
                ],
            ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('availability.index'));

        $schedule = AvailabilitySchedule::firstOrFail();
        $this->assertSame(AvailabilityScope::Organization, $schedule->scope_type);
        $this->assertSame('America/Toronto', $schedule->timezone);
        $this->assertCount(2, $schedule->rules);
    }

    public function test_overlapping_weekly_rules_are_rejected(): void
    {
        [$user, $organization] = $this->ownerContext();

        $response = $this->actingAs($user)
            ->withSession(['active_organization_uuid' => $organization->uuid])
            ->put(route('availability.organization.update'), [
                'timezone' => 'America/Toronto',
                'is_active' => '1',
                'rules' => [
                    ['weekday' => 1, 'start_time' => '09:00', 'end_time' => '12:00'],
                    ['weekday' => 1, 'start_time' => '11:00', 'end_time' => '13:00'],
                ],
            ]);

        $response->assertSessionHasErrors('rules.1.start_time');
        $this->assertSame(0, AvailabilitySchedule::count());
    }

    public function test_resource_custom_schedule_can_be_reset_to_inherit(): void
    {
        [$user, $organization] = $this->ownerContext();
        $resource = Resource::create([
            'organization_id' => $organization->getKey(),
            'person_id' => $user->person_id,
            'type' => 'person',
            'name' => 'Luis',
            'timezone' => 'America/Toronto',
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->withSession(['active_organization_uuid' => $organization->uuid])
            ->put(route('availability.resources.update', $resource), [
                'timezone' => 'America/Toronto',
                'is_active' => '1',
                'rules' => [['weekday' => 1, 'start_time' => '10:00', 'end_time' => '16:00']],
            ])->assertSessionHasNoErrors();

        $this->assertSame(1, AvailabilitySchedule::where('scope_type', 'resource')->count());

        $this->actingAs($user)
            ->withSession(['active_organization_uuid' => $organization->uuid])
            ->delete(route('availability.resources.reset', $resource))
            ->assertRedirect(route('availability.index'));

        $this->assertSame(0, AvailabilitySchedule::where('scope_type', 'resource')->count());
    }

    public function test_resource_availability_edit_uses_timezone_select(): void
    {
        [$user, $organization] = $this->ownerContext();
        $resource = Resource::create([
            'organization_id' => $organization->getKey(),
            'person_id' => $user->person_id,
            'type' => 'person',
            'name' => 'Luis',
            'timezone' => 'America/Toronto',
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)
            ->withSession(['active_organization_uuid' => $organization->uuid])
            ->get(route('availability.resources.edit', $resource));

        $response->assertOk();
        $response->assertSee('<select id="timezone" name="timezone" required>', false);
        $response->assertSee('<option value="America/Toronto" selected>America/Toronto</option>', false);
        $response->assertDontSee('<input id="timezone"', false);
    }

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
