<?php

namespace Tests\Feature;

use App\Enums\MembershipRole;
use App\Enums\MembershipStatus;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\Resource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganizationIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_resource_index_only_shows_active_organization_resources(): void
    {
        $user = User::factory()->create();
        $a = Organization::factory()->create(['name' => 'Org A']);
        $b = Organization::factory()->create(['name' => 'Org B']);

        foreach ([$a, $b] as $org) {
            OrganizationMembership::create([
                'organization_id' => $org->getKey(),
                'person_id' => $user->person_id,
                'role' => MembershipRole::Owner,
                'status' => MembershipStatus::Active,
            ]);
        }

        Resource::create(['organization_id' => $a->getKey(), 'type' => 'person', 'name' => 'Only A', 'is_active' => true]);
        Resource::create(['organization_id' => $b->getKey(), 'type' => 'person', 'name' => 'Only B', 'is_active' => true]);

        $response = $this->actingAs($user)
            ->withSession(['active_organization_uuid' => $a->uuid])
            ->get(route('resources.index'));

        $response->assertOk()->assertSee('Only A')->assertDontSee('Only B');
    }
}
