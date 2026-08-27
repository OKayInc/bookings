<?php

namespace Tests\Feature;

use App\Enums\MembershipRole;
use App\Enums\MembershipStatus;
use App\Models\Organization;
use App\Models\OrganizationMemberInvitation;
use App\Models\OrganizationMembership;
use App\Models\Person;
use App\Models\User;
use App\Notifications\OrganizationMemberInvitationEmail;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class OrganizationMemberInvitationTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_invite_a_non_owner_member(): void
    {
        Notification::fake();
        [$owner, $organization] = $this->ownerContext();

        $this->actingAs($owner)
            ->withSession(['active_organization_uuid' => $organization->uuid])
            ->post(route('organization-members.invitations.store'), [
                'email' => 'New.Member@Example.test',
                'role' => MembershipRole::Employee->value,
            ])
            ->assertRedirect();

        $invitation = OrganizationMemberInvitation::firstOrFail();
        $this->assertSame('new.member@example.test', $invitation->email_normalized);
        $this->assertSame(MembershipRole::Employee, $invitation->role);
        $this->assertTrue($invitation->isPending());
        Notification::assertSentOnDemand(OrganizationMemberInvitationEmail::class);
    }

    public function test_owner_role_cannot_be_granted_by_invitation(): void
    {
        Notification::fake();
        [$owner, $organization] = $this->ownerContext();

        $this->actingAs($owner)
            ->withSession(['active_organization_uuid' => $organization->uuid])
            ->post(route('organization-members.invitations.store'), [
                'email' => 'member@example.test',
                'role' => MembershipRole::Owner->value,
            ])
            ->assertSessionHasErrors('role');

        $this->assertSame(0, OrganizationMemberInvitation::count());
    }

    public function test_new_invitee_creates_an_account_and_joins_without_owning_an_organization(): void
    {
        Notification::fake();
        [, $organization] = $this->ownerContext();
        $token = 'known-new-member-token';
        $invitation = $this->invitation($organization, $token, 'invitee@example.test', MembershipRole::Employee);

        $this->get(route('organization-invitations.show', $token))
            ->assertOk()
            ->assertSee('Create account and join');

        $response = $this->post(route('organization-invitations.accept', $token), [
            'first_name' => 'Invited',
            'last_name' => 'Member',
            'password' => 'MemberPassword123',
            'password_confirmation' => 'MemberPassword123',
            'timezone' => 'America/Toronto',
        ]);

        $response->assertRedirect(route('verification.notice'));
        $user = User::query()->where('email', 'invitee@example.test')->firstOrFail();
        $membership = OrganizationMembership::query()
            ->where('organization_id', $organization->getKey())
            ->where('person_id', $user->person_id)
            ->firstOrFail();

        $this->assertSame(MembershipRole::Employee, $membership->role);
        $this->assertSame(MembershipStatus::Active, $membership->status);
        $this->assertSame(1, Organization::count());
        $this->assertNotNull($invitation->fresh()->accepted_at_utc);
        $this->assertAuthenticatedAs($user);
        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_existing_user_logs_in_and_accepts_without_creating_a_second_person(): void
    {
        Notification::fake();
        [, $organization] = $this->ownerContext();
        $existing = User::factory()->create(['email' => 'existing@example.test']);
        $personCount = Person::count();
        $token = 'known-existing-member-token';
        $invitation = $this->invitation($organization, $token, 'existing@example.test', MembershipRole::Manager);

        $this->actingAs($existing)
            ->post(route('organization-invitations.accept', $token))
            ->assertRedirect(route('dashboard'));

        $this->assertSame($personCount, Person::count());
        $membership = OrganizationMembership::query()
            ->where('organization_id', $organization->getKey())
            ->where('person_id', $existing->person_id)
            ->firstOrFail();
        $this->assertSame(MembershipRole::Manager, $membership->role);
        $this->assertNotNull($invitation->fresh()->accepted_at_utc);
    }

    public function test_invitation_cannot_be_accepted_by_a_different_signed_in_user(): void
    {
        [, $organization] = $this->ownerContext();
        $differentUser = User::factory()->create(['email' => 'different@example.test']);
        $token = 'known-email-bound-token';
        $this->invitation($organization, $token, 'invitee@example.test', MembershipRole::Employee);

        $this->actingAs($differentUser)
            ->post(route('organization-invitations.accept', $token))
            ->assertForbidden();

        $this->assertFalse(OrganizationMembership::query()
            ->where('organization_id', $organization->getKey())
            ->where('person_id', $differentUser->person_id)
            ->exists());
    }

    private function ownerContext(): array
    {
        $owner = User::factory()->create();
        $organization = Organization::factory()->create();
        OrganizationMembership::create([
            'organization_id' => $organization->getKey(),
            'person_id' => $owner->person_id,
            'role' => MembershipRole::Owner,
            'status' => MembershipStatus::Active,
        ]);
        $owner->forceFill(['active_organization_id' => $organization->getKey()])->save();

        return [$owner, $organization];
    }

    private function invitation(
        Organization $organization,
        string $token,
        string $email,
        MembershipRole $role,
    ): OrganizationMemberInvitation {
        return OrganizationMemberInvitation::create([
            'organization_id' => $organization->getKey(),
            'email' => $email,
            'email_normalized' => strtolower($email),
            'role' => $role,
            'token_hash' => hash('sha256', $token, true),
            'expires_at_utc' => now('UTC')->addDay(),
        ]);
    }
}
