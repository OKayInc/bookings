<?php

namespace App\Domain\Conferences;

use App\Enums\ConferenceProvider;
use App\Models\Appointment;
use App\Models\OrganizationConferenceSetting;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class ConferenceMeetingService
{
    public function __construct(private readonly ConferenceProviderCatalog $catalog) {}

    public function safeSync(Appointment $appointment): void
    {
        try {
            $this->sync($appointment);
        } catch (Throwable $exception) {
            report($exception);
            $appointment->forceFill([
                'meeting_status' => 'error',
                'meeting_error' => $this->safeErrorMessage($appointment, $exception),
            ])->save();
        }
    }

    public function sync(Appointment $appointment): void
    {
        $appointment->loadMissing(['appointmentType', 'organization.conferenceSettings']);
        $provider = $appointment->meeting_provider;
        if ($provider === null) {
            return;
        }
        if ($appointment->meeting_status === 'ready' && filled($appointment->meeting_join_url)) {
            return;
        }
        if (! $this->catalog->isConfigured($appointment->organization, $provider)) {
            throw new RuntimeException($provider->label().' is no longer configured for this organization.');
        }

        $settings = $appointment->organization->conferenceSettings;
        $result = match ($provider) {
            ConferenceProvider::Jitsi => $this->jitsi($appointment),
            ConferenceProvider::Custom => $this->custom($settings),
            ConferenceProvider::GoogleMeet => $this->google($settings),
            ConferenceProvider::MicrosoftTeams => $this->microsoft($appointment, $settings),
            ConferenceProvider::Zoom => $this->zoom($appointment, $settings),
            ConferenceProvider::Webex => $this->webex($appointment, $settings),
        };

        $joinUrl = $this->safeHttpUrl($result['join_url'] ?? null);
        if ($joinUrl === null) {
            throw new RuntimeException($provider->label().' did not return a valid meeting URL.');
        }

        $appointment->forceFill([
            'meeting_external_id' => isset($result['external_id']) ? (string) $result['external_id'] : null,
            'meeting_join_url' => $joinUrl,
            'meeting_host_url' => $this->safeHttpUrl($result['host_url'] ?? null),
            'meeting_status' => 'ready',
            'meeting_error' => null,
        ])->save();
    }

    /** @return array{external_id: string, join_url: string} */
    private function jitsi(Appointment $appointment): array
    {
        $room = 'appointment-to-'.str_replace('-', '', (string) $appointment->uuid).'-'.Str::lower(Str::random(12));

        return [
            'external_id' => $room,
            'join_url' => rtrim((string) config('conferences.jitsi_base_url'), '/').'/'.$room,
        ];
    }

    /** @return array{join_url: string} */
    private function custom(?OrganizationConferenceSetting $settings): array
    {
        return ['join_url' => (string) $settings?->custom_meeting_url];
    }

    /** @return array{external_id: string, join_url: string} */
    private function google(?OrganizationConferenceSetting $settings): array
    {
        $token = $this->form()->post((string) config('conferences.google_token_url'), [
            'client_id' => $settings?->google_client_id,
            'client_secret' => $settings?->google_client_secret,
            'refresh_token' => $settings?->google_refresh_token,
            'grant_type' => 'refresh_token',
        ])->throw()->json('access_token');

        $space = $this->api((string) $token)
            ->withBody('{}', 'application/json')
            ->send('POST', (string) config('conferences.google_spaces_url'))
            ->throw()->json();

        return [
            'external_id' => (string) ($space['name'] ?? ''),
            'join_url' => (string) ($space['meetingUri'] ?? ''),
        ];
    }

    /** @return array{external_id: string, join_url: string} */
    private function microsoft(Appointment $appointment, ?OrganizationConferenceSetting $settings): array
    {
        $authority = rtrim((string) config('conferences.microsoft_authority'), '/')
            .'/'.rawurlencode((string) $settings?->microsoft_tenant_id).'/oauth2/v2.0/token';
        $token = $this->form()->post($authority, [
            'client_id' => $settings?->microsoft_client_id,
            'client_secret' => $settings?->microsoft_client_secret,
            'scope' => 'https://graph.microsoft.com/.default',
            'grant_type' => 'client_credentials',
        ])->throw()->json('access_token');

        $url = rtrim((string) config('conferences.microsoft_graph_url'), '/')
            .'/users/'.rawurlencode((string) $settings?->microsoft_organizer_user_id).'/onlineMeetings';
        $meeting = $this->api((string) $token)->post($url, [
            'subject' => $appointment->appointmentType->name,
            'startDateTime' => $appointment->starts_at_utc->utc()->toIso8601String(),
            'endDateTime' => $appointment->ends_at_utc->utc()->toIso8601String(),
        ])->throw()->json();

        return [
            'external_id' => (string) ($meeting['id'] ?? ''),
            'join_url' => (string) ($meeting['joinWebUrl'] ?? ''),
        ];
    }

    /** @return array{external_id: string, join_url: string, host_url: ?string} */
    private function zoom(Appointment $appointment, ?OrganizationConferenceSetting $settings): array
    {
        $token = Http::asForm()
            ->acceptJson()
            ->withBasicAuth((string) $settings?->zoom_client_id, (string) $settings?->zoom_client_secret)
            ->timeout($this->timeout())
            ->post((string) config('conferences.zoom_token_url'), [
                'grant_type' => 'account_credentials',
                'account_id' => $settings?->zoom_account_id,
            ])->throw()->json('access_token');

        $url = rtrim((string) config('conferences.zoom_api_url'), '/')
            .'/users/'.rawurlencode((string) $settings?->zoom_host_user_id).'/meetings';
        $meeting = $this->api((string) $token)->post($url, [
            'topic' => $appointment->appointmentType->name,
            'type' => 2,
            'start_time' => $appointment->starts_at_utc->utc()->format('Y-m-d\TH:i:s\Z'),
            'duration' => max(1, (int) ceil($appointment->starts_at_utc->diffInSeconds($appointment->ends_at_utc) / 60)),
            'timezone' => 'UTC',
            'settings' => ['join_before_host' => true, 'waiting_room' => false],
        ])->throw()->json();

        return [
            'external_id' => (string) ($meeting['id'] ?? ''),
            'join_url' => (string) ($meeting['join_url'] ?? ''),
            'host_url' => isset($meeting['start_url']) ? (string) $meeting['start_url'] : null,
        ];
    }

    /** @return array{external_id: string, join_url: string, host_url: ?string} */
    private function webex(Appointment $appointment, ?OrganizationConferenceSetting $settings): array
    {
        $tokens = $this->form()->post((string) config('conferences.webex_token_url'), [
            'grant_type' => 'refresh_token',
            'client_id' => $settings?->webex_client_id,
            'client_secret' => $settings?->webex_client_secret,
            'refresh_token' => $settings?->webex_refresh_token,
        ])->throw()->json();

        if (filled($tokens['refresh_token'] ?? null) && $settings !== null) {
            $settings->forceFill(['webex_refresh_token' => (string) $tokens['refresh_token']])->save();
        }

        $meeting = $this->api((string) ($tokens['access_token'] ?? ''))
            ->post(rtrim((string) config('conferences.webex_api_url'), '/').'/meetings', [
                'title' => $appointment->appointmentType->name,
                'start' => $appointment->starts_at_utc->utc()->toIso8601String(),
                'end' => $appointment->ends_at_utc->utc()->toIso8601String(),
                'timezone' => 'UTC',
                'hostEmail' => $settings?->webex_host_email,
            ])->throw()->json();

        return [
            'external_id' => (string) ($meeting['id'] ?? ''),
            'join_url' => (string) ($meeting['webLink'] ?? ''),
            'host_url' => isset($meeting['hostWebLink']) ? (string) $meeting['hostWebLink'] : null,
        ];
    }

    private function api(string $token): PendingRequest
    {
        if ($token === '') {
            throw new RuntimeException('The conference provider did not return an access token.');
        }

        return Http::acceptJson()->asJson()->withToken($token)->timeout($this->timeout());
    }

    private function form(): PendingRequest
    {
        return Http::asForm()->acceptJson()->timeout($this->timeout());
    }

    private function timeout(): int
    {
        return max(1, (int) config('conferences.http_timeout_seconds', 10));
    }

    private function safeHttpUrl(mixed $url): ?string
    {
        if (! is_string($url) || $url === '') {
            return null;
        }
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        return in_array($scheme, ['http', 'https'], true) ? $url : null;
    }

    private function safeErrorMessage(Appointment $appointment, Throwable $exception): string
    {
        if ($exception instanceof RequestException) {
            $provider = $appointment->meeting_provider?->label() ?? 'Conference provider';
            $status = $exception->response->status();

            return $provider.' request failed (HTTP '.$status.').';
        }

        return Str::limit($exception->getMessage(), 5000, '');
    }
}
