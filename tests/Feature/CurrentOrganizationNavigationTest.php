<?php

namespace Tests\Feature;

use App\Enums\MembershipRole;
use App\Enums\MembershipStatus;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CurrentOrganizationNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_backend_navigation_shows_current_organization_name(): void
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create(['name' => 'More Than Photos']);

        OrganizationMembership::create([
            'organization_id' => $organization->getKey(),
            'person_id' => $user->person_id,
            'role' => MembershipRole::Owner,
            'status' => MembershipStatus::Active,
        ]);

        $response = $this->actingAs($user)
            ->withSession(['active_organization_uuid' => $organization->uuid])
            ->get(route('dashboard'));

        $response->assertOk()
            ->assertSee('Organization:')
            ->assertSee('More Than Photos')
            ->assertSee('Log out');
    }

    public function test_organization_management_page_uses_session_to_show_current_organization(): void
    {
        $user = User::factory()->create();
        $a = Organization::factory()->create(['name' => 'Organization Alpha']);
        $b = Organization::factory()->create(['name' => 'Organization Beta']);

        foreach ([$a, $b] as $organization) {
            OrganizationMembership::create([
                'organization_id' => $organization->getKey(),
                'person_id' => $user->person_id,
                'role' => MembershipRole::Owner,
                'status' => MembershipStatus::Active,
            ]);
        }

        $response = $this->actingAs($user)
            ->withSession(['active_organization_uuid' => $b->uuid])
            ->get(route('organizations.index'));

        $response->assertOk()
            ->assertSee('Organization:')
            ->assertSee('Organization Beta');
    }

    public function test_switching_organization_updates_navigation_indicator(): void
    {
        $user = User::factory()->create();
        $a = Organization::factory()->create(['name' => 'Organization Alpha']);
        $b = Organization::factory()->create(['name' => 'Organization Beta']);

        foreach ([$a, $b] as $organization) {
            OrganizationMembership::create([
                'organization_id' => $organization->getKey(),
                'person_id' => $user->person_id,
                'role' => MembershipRole::Owner,
                'status' => MembershipStatus::Active,
            ]);
        }

        $this->actingAs($user)
            ->withSession(['active_organization_uuid' => $a->uuid])
            ->post(route('organizations.switch', $b))
            ->assertRedirect(route('dashboard'));

        $response = $this->get(route('dashboard'));

        $response->assertOk()
            ->assertSee('Organization Beta')
            ->assertDontSee('Organization Alpha');
    }
}
