<?php

namespace Tests\Feature;

use App\Enums\MembershipRole;
use App\Enums\MembershipStatus;
use App\Models\AppointmentType;
use App\Models\Organization;
use App\Models\OrganizationConferenceSetting;
use App\Models\OrganizationMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ConferenceConfigurationTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_save_organization_scoped_conference_credentials_encrypted(): void
    {
        [$owner, $organization] = $this->context(MembershipRole::Owner);

        $response = $this->actingAs($owner)
            ->withSession(['active_organization_uuid' => $organization->uuid])
            ->put(route('settings.update'), [
                'google_maps_api_key' => 'maps-key',
                'google_routes_api_key' => 'routes-key',
                'google_client_id' => 'google-client',
                'google_client_secret' => 'google-secret',
                'google_refresh_token' => 'google-refresh',
                'microsoft_tenant_id' => 'tenant-id',
                'microsoft_client_id' => 'teams-client',
                'microsoft_client_secret' => 'teams-secret',
                'microsoft_organizer_user_id' => 'organizer@example.test',
                'zoom_account_id' => 'zoom-account',
                'zoom_client_id' => 'zoom-client',
                'zoom_client_secret' => 'zoom-secret',
                'zoom_host_user_id' => 'zoom-host@example.test',
                'webex_client_id' => 'webex-client',
                'webex_client_secret' => 'webex-secret',
                'webex_refresh_token' => 'webex-refresh',
                'webex_host_email' => 'webex-host@example.test',
                'custom_meeting_url' => 'https://meet.example.test/private-room',
            ]);

        $response->assertSessionHasNoErrors()->assertRedirect(route('settings.edit'));
        $settings = OrganizationConferenceSetting::query()->firstOrFail();
        $this->assertTrue(hash_equals($organization->getKey(), $settings->organization_id));
        $this->assertSame('google-secret', $settings->google_client_secret);
        $this->assertSame('maps-key', $settings->google_maps_api_key);
        $this->assertSame('routes-key', $settings->google_routes_api_key);
        $this->assertSame('teams-secret', $settings->microsoft_client_secret);
        $this->assertSame('zoom-secret', $settings->zoom_client_secret);
        $this->assertSame('webex-refresh', $settings->webex_refresh_token);
        $this->assertSame('https://meet.example.test/private-room', $settings->custom_meeting_url);

        $raw = DB::table('organization_conference_settings')->first();
        $this->assertNotSame('google-secret', $raw->google_client_secret);
        $this->assertNotSame('maps-key', $raw->google_maps_api_key);
        $this->assertNotSame('routes-key', $raw->google_routes_api_key);
        $this->assertNotSame('teams-secret', $raw->microsoft_client_secret);
        $this->assertNotSame('zoom-secret', $raw->zoom_client_secret);
        $this->assertNotSame('webex-refresh', $raw->webex_refresh_token);
        $this->assertNotSame('https://meet.example.test/private-room', $raw->custom_meeting_url);

        $this->actingAs($owner)
            ->withSession(['active_organization_uuid' => $organization->uuid])
            ->put(route('settings.update'), ['google_client_id' => 'google-client-updated'])
            ->assertSessionHasNoErrors();

        $settings->refresh();
        $this->assertSame('google-client-updated', $settings->google_client_id);
        $this->assertSame('google-secret', $settings->google_client_secret);
        $this->assertSame('google-refresh', $settings->google_refresh_token);

        $this->actingAs($owner)
            ->withSession(['active_organization_uuid' => $organization->uuid])
            ->get(route('settings.edit'))
            ->assertOk()
            ->assertDontSee('maps-key')
            ->assertDontSee('routes-key')
            ->assertDontSee('google-secret')
            ->assertDontSee('google-refresh')
            ->assertDontSee('https://meet.example.test/private-room');
    }

    public function test_manager_cannot_open_or_update_organization_credentials(): void
    {
        [$manager, $organization] = $this->context(MembershipRole::Manager);

        $this->actingAs($manager)
            ->withSession(['active_organization_uuid' => $organization->uuid])
            ->get(route('settings.edit'))
            ->assertForbidden();

        $this->actingAs($manager)
            ->withSession(['active_organization_uuid' => $organization->uuid])
            ->put(route('settings.update'), ['zoom_client_secret' => 'not-allowed'])
            ->assertForbidden();

        $this->assertDatabaseCount('organization_conference_settings', 0);
    }

    public function test_jitsi_is_always_selectable_but_unconfigured_remote_provider_is_rejected(): void
    {
        [$owner, $organization] = $this->context(MembershipRole::Owner);

        $this->actingAs($owner)
            ->withSession(['active_organization_uuid' => $organization->uuid])
            ->post(route('appointment-types.store'), $this->typeData('jitsi'))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('appointment-types.index'));

        $type = AppointmentType::query()->firstOrFail();
        $this->assertTrue($type->is_online);
        $this->assertSame('jitsi', $type->meeting_provider->value);

        $this->actingAs($owner)
            ->withSession(['active_organization_uuid' => $organization->uuid])
            ->post(route('appointment-types.store'), $this->typeData('zoom', 'Unconfigured Zoom'))
            ->assertSessionHasErrors('meeting_provider');

        $this->assertDatabaseCount('appointment_types', 1);
    }

    public function test_configured_zoom_can_be_selected_and_offline_type_clears_provider(): void
    {
        [$owner, $organization] = $this->context(MembershipRole::Owner);
        OrganizationConferenceSetting::create([
            'organization_id' => $organization->getKey(),
            'zoom_account_id' => 'account',
            'zoom_client_id' => 'client',
            'zoom_client_secret' => 'secret',
            'zoom_host_user_id' => 'host@example.test',
        ]);

        $this->actingAs($owner)
            ->withSession(['active_organization_uuid' => $organization->uuid])
            ->post(route('appointment-types.store'), $this->typeData('zoom'))
            ->assertSessionHasNoErrors();

        $online = AppointmentType::query()->firstOrFail();
        $this->assertTrue($online->is_online);
        $this->assertSame('zoom', $online->meeting_provider->value);

        $offline = $this->typeData('zoom', 'Offline');
        $offline['is_online'] = '0';
        $this->actingAs($owner)
            ->withSession(['active_organization_uuid' => $organization->uuid])
            ->post(route('appointment-types.store'), $offline)
            ->assertSessionHasNoErrors();

        $savedOffline = AppointmentType::query()->where('name', 'Offline')->firstOrFail();
        $this->assertFalse($savedOffline->is_online);
        $this->assertNull($savedOffline->meeting_provider);
    }

    /** @return array<string, mixed> */
    private function typeData(string $provider, string $name = 'Online consultation'): array
    {
        return [
            'name' => $name,
            'visibility' => 'public',
            'attendance_mode' => 'single',
            'is_online' => '1',
            'meeting_provider' => $provider,
            'duration_mode' => 'fixed',
            'duration_unit' => 'minute',
            'duration_value' => 60,
            'buffer_before_minutes' => 0,
            'buffer_after_minutes' => 0,
            'pricing_mode' => 'free',
            'is_active' => '1',
        ];
    }

    /** @return array{User, Organization} */
    private function context(MembershipRole $role): array
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create();
        OrganizationMembership::create([
            'organization_id' => $organization->getKey(),
            'person_id' => $user->person_id,
            'role' => $role,
            'status' => MembershipStatus::Active,
        ]);

        return [$user, $organization];
    }
}
