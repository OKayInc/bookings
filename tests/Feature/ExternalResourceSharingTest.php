<?php

namespace Tests\Feature;

use App\Enums\MembershipRole;
use App\Enums\MembershipStatus;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\Resource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ExternalResourceSharingTest extends TestCase
{
    use RefreshDatabase;

    public function test_separate_owners_can_share_and_revoke_without_granting_membership(): void
    {
        [$sender, $source] = $this->ownedOrganization();
        [$recipient, $target] = $this->ownedOrganization();
        [$stranger, $other] = $this->ownedOrganization();
        $resource = Resource::create(['organization_id' => $source->getKey(), 'type' => 'room', 'name' => 'Room1', 'is_active' => true]);

        $this->actingAs($sender)->withSession(['active_organization_uuid' => $source->uuid])
            ->post(route('resource-sharing.invite'), ['resource_uuid' => $resource->uuid, 'email' => $recipient->email])
            ->assertRedirect(route('resource-sharing.index'))->assertSessionHas('share_code');
        $code = session('share_code');
        $this->assertFalse($resource->isAvailableToOrganization($target));
        $this->assertDatabaseHas('resource_share_invitations', ['token_hash' => hash('sha256', $code), 'status' => 'pending']);

        $this->actingAs($stranger)->withSession(['active_organization_uuid' => $other->uuid])
            ->post(route('resource-sharing.accept'), ['code' => $code])->assertSessionHasErrors('sharing');
        $this->assertFalse($resource->isAvailableToOrganization($other));

        $this->actingAs($recipient)->withSession(['active_organization_uuid' => $target->uuid])
            ->post(route('resource-sharing.accept'), ['code' => $code])->assertRedirect(route('resource-sharing.index'))->assertSessionHasNoErrors();
        $this->assertTrue($resource->isAvailableToOrganization($target));
        $this->assertFalse($source->memberships()->where('person_id', $recipient->person_id)->exists());
        $this->assertEquals($source->getKey(), $resource->fresh()->organization_id);

        $this->post(route('resource-sharing.accept'), ['code' => $code])->assertSessionHasErrors('sharing');
        $this->get(route('resources.edit', $resource))->assertNotFound();
        $share = DB::table('resource_share_invitations')->where('token_hash', hash('sha256', $code))->first();

        $this->actingAs($sender)->withSession(['active_organization_uuid' => $source->uuid])
            ->put(route('resources.update', $resource), ['name' => 'Room1 updated', 'type' => 'room', 'default_requirement' => 'required', 'is_active' => '1'])
            ->assertRedirect(route('resources.index'))->assertSessionHasNoErrors();
        $this->assertTrue($resource->isAvailableToOrganization($target));

        $this->actingAs($stranger)->withSession(['active_organization_uuid' => $other->uuid])
            ->post(route('resource-sharing.revoke', $share->id))->assertNotFound();
        $this->actingAs($recipient)->withSession(['active_organization_uuid' => $target->uuid])
            ->post(route('resource-sharing.revoke', $share->id))->assertRedirect(route('resource-sharing.index'))->assertSessionHasNoErrors();
        $this->assertFalse($resource->isAvailableToOrganization($target));
        $this->assertTrue($resource->isAvailableToOrganization($source));
    }

    public function test_expired_invitation_cannot_be_accepted(): void
    {
        [$sender, $source] = $this->ownedOrganization();
        [$recipient, $target] = $this->ownedOrganization();
        $resource = Resource::create(['organization_id' => $source->getKey(), 'type' => 'room', 'name' => 'Room1', 'is_active' => true]);
        $this->actingAs($sender)->withSession(['active_organization_uuid' => $source->uuid])
            ->post(route('resource-sharing.invite'), ['resource_uuid' => $resource->uuid, 'email' => $recipient->email]);
        $code = session('share_code');
        $this->travel(8)->days();
        $this->actingAs($recipient)->withSession(['active_organization_uuid' => $target->uuid])
            ->post(route('resource-sharing.accept'), ['code' => $code])->assertSessionHasErrors('sharing');
        $this->assertFalse($resource->isAvailableToOrganization($target));
    }

    private function ownedOrganization(): array
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create(['currency' => 'CAD', 'timezone' => 'America/Toronto']);
        OrganizationMembership::create([
            'organization_id' => $organization->getKey(), 'person_id' => $user->person_id,
            'role' => MembershipRole::Owner, 'status' => MembershipStatus::Active,
        ]);
        return [$user, $organization];
    }
}
