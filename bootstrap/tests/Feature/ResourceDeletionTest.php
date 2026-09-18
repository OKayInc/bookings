<?php

namespace Tests\Feature;

use App\Enums\AppointmentStatus;
use App\Enums\MembershipRole;
use App\Enums\MembershipStatus;
use App\Models\Appointment;
use App\Models\AppointmentType;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\Resource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResourceDeletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_delete_option_appears_for_an_unused_owned_resource_and_deletes_it(): void
    {
        [$user, $organization] = $this->ownerContext();
        $resource = $this->resource($organization, 'Unused tripod');

        $this->actingAs($user)
            ->withSession(['active_organization_uuid' => $organization->uuid])
            ->get(route('resources.index'))
            ->assertOk()
            ->assertSee('action="'.route('resources.destroy', $resource).'"', false);

        $this->actingAs($user)
            ->withSession(['active_organization_uuid' => $organization->uuid])
            ->delete(route('resources.destroy', $resource))
            ->assertRedirect(route('resources.index'))
            ->assertSessionHas('success', 'Unused resource deleted.');

        $this->assertDatabaseMissing('resources', ['id' => $resource->getKey()]);
    }

    public function test_used_resource_has_no_delete_option_and_server_rejects_deletion(): void
    {
        [$user, $organization] = $this->ownerContext();
        $resource = $this->resource($organization, 'Historical camera');
        $type = AppointmentType::create([
            'organization_id' => $organization->getKey(),
            'name' => 'Historical session',
            'slug' => 'historical-session',
            'visibility' => 'public',
            'attendance_mode' => 'single',
            'capacity' => 1,
            'duration_mode' => 'fixed',
            'duration_unit' => 'minute',
            'duration_value' => 60,
            'buffer_before_minutes' => 0,
            'buffer_after_minutes' => 0,
            'pricing_mode' => 'free',
            'is_active' => true,
        ]);
        $appointment = Appointment::create([
            'organization_id' => $organization->getKey(),
            'appointment_type_id' => $type->getKey(),
            'starts_at_utc' => '2026-09-10 13:00:00',
            'ends_at_utc' => '2026-09-10 14:00:00',
            'blocked_starts_at_utc' => '2026-09-10 13:00:00',
            'blocked_ends_at_utc' => '2026-09-10 14:00:00',
            'scheduling_timezone' => 'UTC',
            'duration_value' => 60,
            'capacity' => 1,
            'status' => AppointmentStatus::Scheduled,
        ]);
        $appointment->resources()->attach($resource->getKey(), ['is_required' => true]);

        $this->actingAs($user)
            ->withSession(['active_organization_uuid' => $organization->uuid])
            ->get(route('resources.index'))
            ->assertOk()
            ->assertDontSee('action="'.route('resources.destroy', $resource).'"', false);

        $this->actingAs($user)
            ->withSession(['active_organization_uuid' => $organization->uuid])
            ->delete(route('resources.destroy', $resource))
            ->assertRedirect(route('resources.index'))
            ->assertSessionHasErrors('resource');

        $this->assertDatabaseHas('resources', ['id' => $resource->getKey()]);
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

    private function resource(Organization $organization, string $name): Resource
    {
        return Resource::create([
            'organization_id' => $organization->getKey(),
            'type' => 'equipment',
            'quantity_enabled' => false,
            'inventory_quantity' => 1,
            'name' => $name,
            'timezone' => 'UTC',
            'is_active' => true,
            'is_required_by_default' => true,
        ]);
    }
}
