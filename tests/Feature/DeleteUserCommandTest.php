<?php

namespace Tests\Feature;

use App\Enums\MembershipRole;
use App\Enums\MembershipStatus;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeleteUserCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_dry_run_keeps_user_and_organizations(): void
    {
        $user = User::factory()->create();
        $organization = $this->ownedOrganization($user);

        $this->artisan('users:delete', [
            'user' => $user->email, '--with-organizations' => true, '--dry-run' => true,
        ])->assertExitCode(0);

        $this->assertTrue(User::whereUuid($user->uuid)->exists());
        $this->assertTrue(Organization::whereUuid($organization->uuid)->exists());
    }

    public function test_owned_organization_requires_explicit_option(): void
    {
        $user = User::factory()->create();
        $this->ownedOrganization($user);

        $this->artisan('users:delete', ['user' => $user->email, '--dry-run' => true])
            ->assertExitCode(1);
    }

    public function test_coowned_organization_cannot_be_deleted(): void
    {
        $user = User::factory()->create();
        $coowner = User::factory()->create();
        $organization = $this->ownedOrganization($user);
        OrganizationMembership::create([
            'organization_id' => $organization->getKey(),
            'person_id' => $coowner->person_id,
            'role' => MembershipRole::Owner,
            'status' => MembershipStatus::Active,
        ]);

        $this->artisan('users:delete', [
            'user' => $user->uuid, '--with-organizations' => true, '--dry-run' => true,
        ])->assertExitCode(1);

        $this->assertTrue(Organization::whereUuid($organization->uuid)->exists());
    }

    private function ownedOrganization(User $user): Organization
    {
        $organization = Organization::factory()->create();
        OrganizationMembership::create([
            'organization_id' => $organization->getKey(),
            'person_id' => $user->person_id,
            'role' => MembershipRole::Owner,
            'status' => MembershipStatus::Active,
        ]);
        return $organization;
    }
}
