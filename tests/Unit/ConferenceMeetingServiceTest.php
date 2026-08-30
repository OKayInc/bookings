<?php

namespace Tests\Unit;

use App\Domain\Conferences\ConferenceMeetingService;
use App\Enums\ConferenceProvider;
use App\Models\Appointment;
use App\Models\AppointmentType;
use App\Models\Organization;
use App\Models\OrganizationConferenceSetting;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ConferenceMeetingServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_jitsi_and_custom_links_are_created_without_external_requests(): void
    {
        Http::preventStrayRequests();
        [$organization, $jitsi] = $this->appointment(ConferenceProvider::Jitsi);
        app(ConferenceMeetingService::class)->sync($jitsi);
        $this->assertSame('ready', $jitsi->refresh()->meeting_status);
        $this->assertStringStartsWith('https://meet.jit.si/appointment-to-', $jitsi->meeting_join_url);

        OrganizationConferenceSetting::create([
            'organization_id' => $organization->getKey(),
            'custom_meeting_url' => 'https://conference.example.test/permanent-room',
        ]);
        [, $custom] = $this->appointment(ConferenceProvider::Custom, $organization);
        app(ConferenceMeetingService::class)->sync($custom);
        $this->assertSame('https://conference.example.test/permanent-room', $custom->refresh()->meeting_join_url);
    }

    public function test_google_meet_uses_refresh_token_and_creates_a_space(): void
    {
        [$organization, $appointment] = $this->appointment(ConferenceProvider::GoogleMeet);
        OrganizationConferenceSetting::create([
            'organization_id' => $organization->getKey(),
            'google_client_id' => 'google-client',
            'google_client_secret' => 'google-secret',
            'google_refresh_token' => 'google-refresh',
        ]);
        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response(['access_token' => 'google-access']),
            'https://meet.googleapis.com/v2/spaces' => Http::response([
                'name' => 'spaces/google-space',
                'meetingUri' => 'https://meet.google.com/abc-defg-hij',
            ]),
        ]);

        app(ConferenceMeetingService::class)->sync($appointment);
        $appointment->refresh();
        $this->assertSame('ready', $appointment->meeting_status);
        $this->assertSame('spaces/google-space', $appointment->meeting_external_id);
        $this->assertSame('https://meet.google.com/abc-defg-hij', $appointment->meeting_join_url);
        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://oauth2.googleapis.com/token'
            && $request['grant_type'] === 'refresh_token'
            && $request['refresh_token'] === 'google-refresh');
        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://meet.googleapis.com/v2/spaces'
            && $request->hasHeader('Authorization', 'Bearer google-access')
            && $request->body() === '{}');
    }

    public function test_microsoft_teams_uses_application_credentials_and_organizer(): void
    {
        [$organization, $appointment] = $this->appointment(ConferenceProvider::MicrosoftTeams);
        OrganizationConferenceSetting::create([
            'organization_id' => $organization->getKey(),
            'microsoft_tenant_id' => 'tenant',
            'microsoft_client_id' => 'teams-client',
            'microsoft_client_secret' => 'teams-secret',
            'microsoft_organizer_user_id' => 'organizer@example.test',
        ]);
        Http::fake([
            'https://login.microsoftonline.com/tenant/oauth2/v2.0/token' => Http::response(['access_token' => 'teams-access']),
            'https://graph.microsoft.com/v1.0/users/*/onlineMeetings' => Http::response([
                'id' => 'teams-meeting-id',
                'joinWebUrl' => 'https://teams.microsoft.com/l/meetup-join/example',
            ]),
        ]);

        app(ConferenceMeetingService::class)->sync($appointment);
        $appointment->refresh();
        $this->assertSame('teams-meeting-id', $appointment->meeting_external_id);
        $this->assertSame('https://teams.microsoft.com/l/meetup-join/example', $appointment->meeting_join_url);
        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/onlineMeetings')
            && $request['subject'] === 'Online appointment');
    }

    public function test_zoom_uses_server_to_server_oauth_and_keeps_host_url_private(): void
    {
        [$organization, $appointment] = $this->appointment(ConferenceProvider::Zoom);
        OrganizationConferenceSetting::create([
            'organization_id' => $organization->getKey(),
            'zoom_account_id' => 'zoom-account',
            'zoom_client_id' => 'zoom-client',
            'zoom_client_secret' => 'zoom-secret',
            'zoom_host_user_id' => 'host@example.test',
        ]);
        Http::fake([
            'https://zoom.us/oauth/token' => Http::response(['access_token' => 'zoom-access']),
            'https://api.zoom.us/v2/users/*/meetings' => Http::response([
                'id' => 123456789,
                'join_url' => 'https://zoom.us/j/123456789',
                'start_url' => 'https://zoom.us/s/123456789?zak=private',
            ]),
        ]);

        app(ConferenceMeetingService::class)->sync($appointment);
        $appointment->refresh();
        $this->assertSame('123456789', $appointment->meeting_external_id);
        $this->assertSame('https://zoom.us/j/123456789', $appointment->meeting_join_url);
        $this->assertSame('https://zoom.us/s/123456789?zak=private', $appointment->meeting_host_url);
        $rawJoinUrl = DB::table('appointments')->where('id', $appointment->getKey())->value('meeting_join_url');
        $rawHostUrl = DB::table('appointments')->where('id', $appointment->getKey())->value('meeting_host_url');
        $this->assertNotSame($appointment->meeting_join_url, $rawJoinUrl);
        $this->assertNotSame($appointment->meeting_host_url, $rawHostUrl);
    }

    public function test_webex_rotates_refresh_token_and_creates_meeting(): void
    {
        [$organization, $appointment] = $this->appointment(ConferenceProvider::Webex);
        $settings = OrganizationConferenceSetting::create([
            'organization_id' => $organization->getKey(),
            'webex_client_id' => 'webex-client',
            'webex_client_secret' => 'webex-secret',
            'webex_refresh_token' => 'old-refresh',
            'webex_host_email' => 'host@example.test',
        ]);
        Http::fake([
            'https://webexapis.com/v1/access_token' => Http::response([
                'access_token' => 'webex-access',
                'refresh_token' => 'rotated-refresh',
            ]),
            'https://webexapis.com/v1/meetings' => Http::response([
                'id' => 'webex-meeting-id',
                'webLink' => 'https://example.webex.com/meet/example',
                'hostWebLink' => 'https://example.webex.com/host/private',
            ]),
        ]);

        app(ConferenceMeetingService::class)->sync($appointment);
        $this->assertSame('rotated-refresh', $settings->refresh()->webex_refresh_token);
        $this->assertSame('https://example.webex.com/meet/example', $appointment->refresh()->meeting_join_url);
    }

    public function test_provider_failure_is_recorded_without_throwing_from_safe_sync(): void
    {
        [$organization, $appointment] = $this->appointment(ConferenceProvider::GoogleMeet);
        OrganizationConferenceSetting::create([
            'organization_id' => $organization->getKey(),
            'google_client_id' => 'client',
            'google_client_secret' => 'secret',
            'google_refresh_token' => 'refresh',
        ]);
        Http::fake(['*' => Http::response(['error' => 'provider unavailable'], 503)]);

        app(ConferenceMeetingService::class)->safeSync($appointment);

        $this->assertSame('error', $appointment->refresh()->meeting_status);
        $this->assertSame('Google Meet request failed (HTTP 503).', $appointment->meeting_error);
        $this->assertStringNotContainsString('provider unavailable', $appointment->meeting_error);
        $this->assertNull($appointment->meeting_join_url);
    }

    /** @return array{Organization, Appointment} */
    private function appointment(ConferenceProvider $provider, ?Organization $organization = null): array
    {
        $organization ??= Organization::factory()->create();
        $type = AppointmentType::create([
            'organization_id' => $organization->getKey(),
            'name' => 'Online appointment',
            'slug' => 'online-'.str_replace('_', '-', $provider->value).'-'.bin2hex(random_bytes(3)),
            'visibility' => 'public',
            'attendance_mode' => 'single',
            'is_online' => true,
            'meeting_provider' => $provider->value,
            'capacity' => 1,
            'duration_mode' => 'fixed',
            'duration_unit' => 'minute',
            'duration_value' => 60,
            'buffer_before_minutes' => 0,
            'buffer_after_minutes' => 0,
            'pricing_mode' => 'free',
            'is_active' => true,
        ]);
        $starts = CarbonImmutable::now('UTC')->addDay()->startOfHour();
        $appointment = Appointment::create([
            'organization_id' => $organization->getKey(),
            'appointment_type_id' => $type->getKey(),
            'starts_at_utc' => $starts,
            'ends_at_utc' => $starts->addHour(),
            'blocked_starts_at_utc' => $starts,
            'blocked_ends_at_utc' => $starts->addHour(),
            'scheduling_timezone' => 'UTC',
            'duration_value' => 60,
            'capacity' => 1,
            'status' => 'scheduled',
            'meeting_provider' => $provider->value,
            'meeting_status' => 'pending',
        ]);

        return [$organization, $appointment];
    }
}
