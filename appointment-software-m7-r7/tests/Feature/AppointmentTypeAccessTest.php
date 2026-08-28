<?php

namespace Tests\Feature;

use App\Enums\AppointmentVisibility;
use App\Enums\MembershipRole;
use App\Enums\MembershipStatus;
use App\Models\AppointmentType;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AppointmentTypeAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_unlisted_type_requires_its_secret_link(): void
    {
        $organization = Organization::factory()->create(['slug' => 'demo']);
        $type = $this->type($organization, 'Secret Session', AppointmentVisibility::Unlisted, [
            'public_token' => 'secret-token-123',
        ]);

        $this->get(route('public.appointment-types.show', [
            'organizationSlug' => $organization->slug,
            'appointmentSlug' => $type->slug,
        ]))->assertNotFound();

        $this->get(route('public.appointment-types.unlisted', [
            'organizationSlug' => $organization->slug,
            'token' => 'wrong',
        ]))->assertNotFound();

        $this->get(route('public.appointment-types.unlisted', [
            'organizationSlug' => $organization->slug,
            'token' => 'secret-token-123',
        ]))->assertOk()->assertSee('Secret Session');
    }

    public function test_password_protected_type_unlocks_into_session(): void
    {
        $organization = Organization::factory()->create(['slug' => 'demo']);
        $type = $this->type($organization, 'Protected Session', AppointmentVisibility::PasswordProtected, [
            'access_password' => Hash::make('CorrectHorse123'),
        ]);

        $url = route('public.appointment-types.show', [
            'organizationSlug' => $organization->slug,
            'appointmentSlug' => $type->slug,
        ]);

        $this->get($url)->assertOk()->assertSee('password protected');

        $this->post(route('public.appointment-types.unlock', [
            'organizationSlug' => $organization->slug,
            'appointmentSlug' => $type->slug,
        ]), ['access_password' => 'wrong'])->assertSessionHasErrors('access_password');

        $this->post(route('public.appointment-types.unlock', [
            'organizationSlug' => $organization->slug,
            'appointmentSlug' => $type->slug,
        ]), ['access_password' => 'CorrectHorse123'])->assertRedirect($url);

        $this->get($url)->assertOk()->assertSee('Booking');
    }

    public function test_invitation_link_can_be_created_opened_and_revoked(): void
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create(['slug' => 'demo']);
        OrganizationMembership::create([
            'organization_id' => $organization->getKey(),
            'person_id' => $user->person_id,
            'role' => MembershipRole::Owner,
            'status' => MembershipStatus::Active,
        ]);
        $type = $this->type($organization, 'Invite Session', AppointmentVisibility::InviteOnly);

        $response = $this->actingAs($user)
            ->withSession(['active_organization_uuid' => $organization->uuid])
            ->post(route('appointment-types.invitations.store', $type), [
                'recipient_email' => 'client@example.test',
                'max_uses' => 2,
            ]);

        $response->assertRedirect();
        $url = session('invitation_url');
        $this->assertIsString($url);
        $this->get($url)->assertOk()->assertSee('Invite Session')->assertSee('Recipient-specific invitation');

        $invitation = $type->invitations()->firstOrFail();
        $this->actingAs($user)
            ->withSession(['active_organization_uuid' => $organization->uuid])
            ->delete(route('appointment-types.invitations.destroy', [$type, $invitation]))
            ->assertRedirect();

        $this->get($url)->assertNotFound();
    }

    private function type(Organization $organization, string $name, AppointmentVisibility $visibility, array $extra = []): AppointmentType
    {
        return AppointmentType::create(array_merge([
            'organization_id' => $organization->getKey(),
            'name' => $name,
            'slug' => (string) str($name)->slug(),
            'visibility' => $visibility,
            'attendance_mode' => 'single',
            'capacity' => 1,
            'duration_mode' => 'fixed',
            'duration_unit' => 'hour',
            'duration_value' => 1,
            'buffer_before_minutes' => 0,
            'buffer_after_minutes' => 0,
            'pricing_mode' => 'free',
            'is_active' => true,
        ], $extra));
    }
}
