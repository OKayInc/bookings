<?php

namespace Tests\Feature;

use App\Enums\MembershipRole;
use App\Enums\MembershipStatus;
use App\Http\Controllers\PlatformUserImpersonationController;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlatformUserImpersonationTest extends TestCase
{
    use RefreshDatabase;

    public function test_platform_owner_sees_user_switcher_and_can_switch_into_another_users_organization(): void
    {
        $owner = User::factory()->create(['email' => 'owner@example.test']);
        $target = User::factory()->create(['email' => 'target@example.test']);

        $ownerOrganization = Organization::factory()->create(['name' => 'Owner Organization']);
        $targetOrganization = Organization::factory()->create(['name' => 'Target Organization']);

        $this->membership($owner, $ownerOrganization);
        $this->membership($target, $targetOrganization);

        config(['plans.platform_owner_user_id' => $owner->uuid]);

        $this->actingAs($owner)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('id="platformUserSwitcher"', false)
            ->assertSee('target@example.test');

        $this->post(route('platform.impersonation.store'), [
            'user_uuid' => $target->uuid,
        ])
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas(PlatformUserImpersonationController::OWNER_SESSION_KEY, $owner->uuid);

        $this->assertAuthenticatedAs($target);

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Impersonating:')
            ->assertSee('target@example.test')
            ->assertSee('Target Organization')
            ->assertDontSee('Owner Organization')
            ->assertSee('Return to owner');
    }

    public function test_non_platform_owner_cannot_switch_users_and_does_not_see_selector(): void
    {
        $owner = User::factory()->create();
        $regular = User::factory()->create(['email' => 'regular@example.test']);
        $target = User::factory()->create();

        $organization = Organization::factory()->create(['name' => 'Regular Organization']);
        $this->membership($regular, $organization);

        config(['plans.platform_owner_user_id' => $owner->uuid]);

        $this->actingAs($regular)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('id="platformUserSwitcher"', false);

        $this->post(route('platform.impersonation.store'), [
            'user_uuid' => $target->uuid,
        ])->assertForbidden();

        $this->assertAuthenticatedAs($regular);
    }

    public function test_platform_owner_can_return_from_impersonation(): void
    {
        $owner = User::factory()->create(['email' => 'owner@example.test']);
        $target = User::factory()->create(['email' => 'target@example.test']);

        $ownerOrganization = Organization::factory()->create(['name' => 'Owner Organization']);
        $targetOrganization = Organization::factory()->create(['name' => 'Target Organization']);

        $this->membership($owner, $ownerOrganization);
        $this->membership($target, $targetOrganization);

        config(['plans.platform_owner_user_id' => $owner->uuid]);

        $this->actingAs($owner)
            ->post(route('platform.impersonation.store'), ['user_uuid' => $target->uuid])
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($target);

        $this->delete(route('platform.impersonation.destroy'))
            ->assertRedirect(route('dashboard'))
            ->assertSessionMissing(PlatformUserImpersonationController::OWNER_SESSION_KEY);

        $this->assertAuthenticatedAs($owner);

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Owner Organization')
            ->assertDontSee('Impersonating:');
    }

    private function membership(User $user, Organization $organization): void
    {
        OrganizationMembership::create([
            'organization_id' => $organization->getKey(),
            'person_id' => $user->person_id,
            'role' => MembershipRole::Owner,
            'status' => MembershipStatus::Active,
        ]);
    }
}
