<?php

namespace Tests\Feature;

use App\Domain\Api\ApiKeyService;
use App\Enums\MembershipRole;
use App\Models\AppointmentType;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class M10ApiTest extends TestCase
{
    use RefreshDatabase;

    private function credentials(string $role = 'owner', string $plan = 'paid'): array
    {
        $user = User::factory()->create();
        $org = Organization::factory()->create(['plan_tier' => $plan]);
        $member = OrganizationMembership::create(['organization_id' => $org->getKey(), 'person_id' => $user->person_id, 'role' => $role, 'status' => 'active']);
        $keys = app(ApiKeyService::class);
        $headers = ['X-CLIENT-API-KEY' => $keys->regenerate($user), 'X-ORGANIZATION-API-KEY' => $keys->regenerate($org)];
        return [$user, $org, $member, $headers];
    }

    private function type(Organization $org): AppointmentType
    {
        return AppointmentType::create(['organization_id' => $org->getKey(), 'name' => 'Session', 'slug' => 'session',
            'visibility' => 'public', 'attendance_mode' => 'single', 'capacity' => 1, 'duration_mode' => 'fixed',
            'duration_unit' => 'minute', 'duration_value' => 60, 'start_interval_minutes' => 60,
            'pricing_mode' => 'free', 'email_verification_mode' => 'none', 'is_active' => true]);
    }

    public function test_both_keys_are_required_even_with_a_browser_session_and_json_errors_are_automatic(): void
    {
        [$user, $org, , $headers] = $this->credentials();
        $this->actingAs($user);
        $this->get('/api/v1/me')->assertUnauthorized()->assertHeader('Content-Type', 'application/json');
        $this->getJson('/api/v1/me', ['X-CLIENT-API-KEY' => $headers['X-CLIENT-API-KEY']])->assertUnauthorized();
        $this->getJson('/api/v1/me', ['X-ORGANIZATION-API-KEY' => $headers['X-ORGANIZATION-API-KEY']])->assertUnauthorized();
        $this->getJson('/api/v1/me', $headers)->assertOk()->assertJsonPath('data.organization_uuid', $org->uuid);
    }

    public function test_only_hashes_are_stored_and_never_serialized(): void
    {
        [$user, $org, , $headers] = $this->credentials();
        $this->assertSame(hash('sha256', $headers['X-CLIENT-API-KEY']), $user->fresh()->api_key_hash);
        $this->assertSame(hash('sha256', $headers['X-ORGANIZATION-API-KEY']), $org->fresh()->api_key_hash);
        $this->assertArrayNotHasKey('api_key_hash', $user->toArray());
        $this->assertArrayNotHasKey('api_key_hash', $org->toArray());
        $this->getJson('/api/v1/me', $headers)->assertDontSee($headers['X-CLIENT-API-KEY'])->assertDontSee($user->api_key_hash);
    }

    public function test_rotation_and_revocation_immediately_invalidate_old_keys(): void
    {
        [$user, $org, , $headers] = $this->credentials();
        $keys = app(ApiKeyService::class);
        $new = $keys->regenerate($user);
        $this->getJson('/api/v1/me', $headers)->assertUnauthorized();
        $headers['X-CLIENT-API-KEY'] = $new;
        $this->getJson('/api/v1/me', $headers)->assertOk();
        $new = $keys->regenerate($org);
        $this->getJson('/api/v1/me', $headers)->assertUnauthorized();
        $headers['X-ORGANIZATION-API-KEY'] = $new;
        $this->getJson('/api/v1/me', $headers)->assertOk();
        $keys->revoke($org);
        $this->getJson('/api/v1/me', $headers)->assertUnauthorized();
    }

    public function test_unrelated_key_pairs_suspended_members_and_free_plans_are_denied(): void
    {
        [$user, $org, $member, $headers] = $this->credentials();
        [, , , $other] = $this->credentials();
        $mixed = $headers; $mixed['X-ORGANIZATION-API-KEY'] = $other['X-ORGANIZATION-API-KEY'];
        $this->getJson('/api/v1/me', $mixed)->assertForbidden();
        $member->update(['status' => 'suspended']);
        $this->getJson('/api/v1/me', $headers)->assertForbidden();
        $member->update(['status' => 'active']); $org->update(['plan_tier' => 'free']);
        $this->getJson('/api/v1/me', $headers)->assertForbidden();
        $org->update(['plan_tier' => 'paid']); $user->forceFill(['email_verified_at' => null])->save();
        $this->getJson('/api/v1/me', $headers)->assertForbidden();
    }

    public function test_roles_are_checked_on_every_request(): void
    {
        [, $org, $member, $headers] = $this->credentials('manager');
        $type = $this->type($org);
        $this->getJson('/api/v1/appointment-types', $headers)->assertOk();
        $this->patchJson('/api/v1/appointment-types/'.$type->uuid.'/disable', [], $headers)->assertOk();
        $member->update(['role' => MembershipRole::Employee]);
        $this->getJson('/api/v1/me', $headers)->assertOk()->assertJsonPath('data.role', 'employee');
        $this->getJson('/api/v1/bookings', $headers)->assertForbidden();
        $this->patchJson('/api/v1/appointment-types/'.$type->uuid.'/disable', [], $headers)->assertForbidden();
    }

    public function test_tenant_scoping_ignores_active_browser_organization_and_rejects_foreign_ids(): void
    {
        [$user, $org, , $headers] = $this->credentials();
        [, $other] = $this->credentials();
        $own = $this->type($org); $foreign = $this->type($other);
        $user->forceFill(['active_organization_id' => $other->getKey()])->save();
        $this->withSession(['active_organization_uuid' => $other->uuid]);
        $this->getJson('/api/v1/appointment-types', $headers)->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.uuid', $own->uuid);
        $this->patchJson('/api/v1/appointment-types/'.$foreign->uuid.'/disable', [], $headers)->assertNotFound();
        $this->assertTrue($foreign->fresh()->is_active);
        $this->getJson('/api/v1/appointment-types/not-a-uuid/availability', $headers)->assertNotFound();
    }

    public function test_query_validation_is_bounded(): void
    {
        [, $org, , $headers] = $this->credentials();
        $type = $this->type($org);
        $this->getJson('/api/v1/bookings?per_page=101', $headers)->assertUnprocessable();
        $this->getJson('/api/v1/appointment-types/'.$type->uuid.'/availability?date=bad', $headers)->assertUnprocessable();
        $this->getJson('/api/v1/appointment-types/'.$type->uuid.'/availability?date=2026-10-01&timezone=invalid', $headers)->assertUnprocessable();
    }

    public function test_web_keys_are_shown_once_and_organization_rotation_requires_admin(): void
    {
        [$user, $org] = $this->credentials('manager');
        $this->actingAs($user)->withSession(['active_organization_uuid' => $org->uuid]);
        $this->get('/api-keys')->assertOk()->assertSee('Client key');
        $this->post('/api-keys', ['kind' => 'organization', 'action' => 'regenerate'])->assertForbidden();
        $response = $this->post('/api-keys', ['kind' => 'client', 'action' => 'regenerate'])->assertOk()->assertViewHas('newKey');
        $plain = $response->viewData('newKey');
        $this->assertSame(hash('sha256', $plain), $user->fresh()->api_key_hash);
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->get('/api-keys')->assertOk()->assertDontSee($plain);
    }

    public function test_owner_can_rotate_organization_key(): void
    {
        [$user, $org] = $this->credentials();
        $old = $org->api_key_hash;
        $this->actingAs($user)->withSession(['active_organization_uuid' => $org->uuid]);
        $this->post('/api-keys', ['kind' => 'organization', 'action' => 'regenerate'])->assertOk();
        $this->assertNotSame($old, $org->fresh()->api_key_hash);
    }

    public function test_authentication_is_rate_limited(): void
    {
        for ($i = 0; $i < 60; $i++) { $this->getJson('/api/v1/me')->assertUnauthorized(); }
        $this->getJson('/api/v1/me')->assertStatus(429);
    }
}
