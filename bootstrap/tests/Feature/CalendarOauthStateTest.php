<?php

namespace Tests\Feature;

use App\Enums\MembershipRole;
use App\Enums\MembershipStatus;
use App\Models\CalendarOauthState;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\Resource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CalendarOauthStateTest extends TestCase
{
    use RefreshDatabase;

    public function test_google_callback_survives_loss_of_laravel_session(): void
    {
        [$user, $organization, $resource] = $this->ownerResource();
        config([
            'calendars.google.client_id' => 'google-client',
            'calendars.google.client_secret' => 'google-secret',
            'calendars.google.redirect_uri' => 'https://appointments.example.test/calendar-connections/oauth/google/callback',
        ]);

        $connect = $this->actingAs($user)
            ->withSession(['active_organization_uuid' => $organization->uuid])
            ->get(route('calendar-connections.connect', [$resource, 'google']));

        $connect->assertRedirect();
        parse_str((string) parse_url($connect->headers->get('Location'), PHP_URL_QUERY), $query);
        $state = (string) ($query['state'] ?? '');
        $this->assertNotSame('', $state);
        $this->assertDatabaseCount('calendar_oauth_states', 1);
        $this->assertSame(hash('sha256', $state), CalendarOauthState::query()->firstOrFail()->state_hash);

        // Simulate returning from Google without the authenticated Laravel session/cookie.
        $this->app['auth']->logout();
        $this->app['session']->flush();

        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'access-token', 'refresh_token' => 'refresh-token', 'expires_in' => 3600,
            ]),
            'https://www.googleapis.com/oauth2/v3/userinfo' => Http::response([
                'sub' => 'google-user-1', 'email' => 'staff@example.test', 'name' => 'Staff User',
            ]),
            'https://www.googleapis.com/calendar/v3/users/me/calendarList*' => Http::response([
                'items' => [[
                    'id' => 'primary@example.test', 'summary' => 'Primary', 'primary' => true,
                    'accessRole' => 'owner', 'timeZone' => 'America/Toronto',
                ]],
            ]),
        ]);

        $this->get(route('calendar-connections.callback', ['provider' => 'google', 'state' => $state, 'code' => 'auth-code']))
            ->assertRedirect(route('login'));

        $this->assertDatabaseCount('calendar_connections', 1);
        $this->assertNotNull(CalendarOauthState::query()->firstOrFail()->consumed_at_utc);
    }

    public function test_oauth_state_is_single_use(): void
    {
        [$user, $organization, $resource] = $this->ownerResource();
        $state = 'single-use-state';
        CalendarOauthState::create([
            'user_id' => $user->getKey(),
            'organization_id' => $organization->getKey(),
            'resource_id' => $resource->getKey(),
            'provider' => 'google',
            'state_hash' => hash('sha256', $state),
            'expires_at_utc' => now('UTC')->addMinutes(15),
            'consumed_at_utc' => now('UTC'),
        ]);

        $this->get(route('calendar-connections.callback', ['provider' => 'google', 'state' => $state, 'code' => 'replay']))
            ->assertStatus(419);
    }

    public function test_expired_oauth_state_is_rejected(): void
    {
        [$user, $organization, $resource] = $this->ownerResource();
        $state = 'expired-state';
        CalendarOauthState::create([
            'user_id' => $user->getKey(),
            'organization_id' => $organization->getKey(),
            'resource_id' => $resource->getKey(),
            'provider' => 'google',
            'state_hash' => hash('sha256', $state),
            'expires_at_utc' => now('UTC')->subSecond(),
        ]);

        $this->get(route('calendar-connections.callback', ['provider' => 'google', 'state' => $state, 'code' => 'late']))
            ->assertStatus(419);
    }

    private function ownerResource(): array
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create();
        OrganizationMembership::create([
            'organization_id' => $organization->getKey(),
            'person_id' => $user->person_id,
            'role' => MembershipRole::Owner,
            'status' => MembershipStatus::Active,
        ]);
        $resource = Resource::create([
            'organization_id' => $organization->getKey(),
            'person_id' => $user->person_id,
            'type' => 'person',
            'name' => 'Calendar Owner',
            'timezone' => 'America/Toronto',
            'is_active' => true,
            'is_required_by_default' => true,
        ]);

        return [$user, $organization, $resource];
    }
}
