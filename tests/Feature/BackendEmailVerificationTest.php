<?php

namespace Tests\Feature;

use App\Enums\MembershipRole;
use App\Enums\MembershipStatus;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class BackendEmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_unverified_backend_user_is_redirected_to_verification_notice(): void
    {
        $user = User::factory()->create(['email_verified_at' => null]);
        $organization = Organization::factory()->create();
        OrganizationMembership::create([
            'organization_id' => $organization->getKey(),
            'person_id' => $user->person_id,
            'role' => MembershipRole::Owner,
            'status' => MembershipStatus::Active,
        ]);

        $this->actingAs($user)
            ->withSession(['active_organization_uuid' => $organization->uuid])
            ->get(route('dashboard'))
            ->assertRedirect(route('verification.notice'));
    }

    public function test_uuid_signed_verification_link_marks_backend_email_verified(): void
    {
        $user = User::factory()->create(['email_verified_at' => null]);
        $organization = Organization::factory()->create();
        OrganizationMembership::create([
            'organization_id' => $organization->getKey(),
            'person_id' => $user->person_id,
            'role' => MembershipRole::Owner,
            'status' => MembershipStatus::Active,
        ]);

        $url = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            [
                'id' => $user->uuid,
                'hash' => sha1($user->email),
            ]
        );

        $this->actingAs($user)
            ->withSession(['active_organization_uuid' => $organization->uuid])
            ->get($url)
            ->assertRedirect(route('dashboard'));

        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_verified_backend_user_can_access_dashboard(): void
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create();
        OrganizationMembership::create([
            'organization_id' => $organization->getKey(),
            'person_id' => $user->person_id,
            'role' => MembershipRole::Owner,
            'status' => MembershipStatus::Active,
        ]);

        $this->actingAs($user)
            ->withSession(['active_organization_uuid' => $organization->uuid])
            ->get(route('dashboard'))
            ->assertOk();
    }
}
