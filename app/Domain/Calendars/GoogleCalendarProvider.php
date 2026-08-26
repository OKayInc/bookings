<?php

namespace App\Domain\Calendars;

use App\Enums\CalendarProvider;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class GoogleCalendarProvider implements CalendarProviderContract
{
    public function provider(): CalendarProvider { return CalendarProvider::Google; }

    public function authorizationUrl(string $state): string
    {
        $config = config('calendars.google');
        $this->assertConfigured($config);
        return 'https://accounts.google.com/o/oauth2/v2/auth?'.http_build_query([
            'client_id' => $config['client_id'], 'redirect_uri' => $config['redirect_uri'],
            'response_type' => 'code', 'scope' => implode(' ', $config['scopes']),
            'access_type' => 'offline', 'include_granted_scopes' => 'true', 'prompt' => 'consent',
            'state' => $state,
        ], '', '&', PHP_QUERY_RFC3986);
    }

    public function exchangeAuthorizationCode(string $code): array
    {
        $config = config('calendars.google'); $this->assertConfigured($config);
        return $this->form()->post('https://oauth2.googleapis.com/token', [
            'code' => $code, 'client_id' => $config['client_id'], 'client_secret' => $config['client_secret'],
            'redirect_uri' => $config['redirect_uri'], 'grant_type' => 'authorization_code',
        ])->throw()->json();
    }

    public function refreshAccessToken(string $refreshToken): array
    {
        $config = config('calendars.google'); $this->assertConfigured($config);
        return $this->form()->post('https://oauth2.googleapis.com/token', [
            'refresh_token' => $refreshToken, 'client_id' => $config['client_id'],
            'client_secret' => $config['client_secret'], 'grant_type' => 'refresh_token',
        ])->throw()->json();
    }

    public function accountProfile(string $accessToken): array
    {
        $json = $this->api($accessToken)->get('https://www.googleapis.com/oauth2/v3/userinfo')->throw()->json();
        return ['id' => $json['sub'] ?? null, 'name' => $json['name'] ?? ($json['email'] ?? null), 'email' => $json['email'] ?? null];
    }

    public function listCalendars(string $accessToken): array
    {
        $items = []; $pageToken = null;
        do {
            $params = ['maxResults' => 250, 'showHidden' => true];
            if ($pageToken) { $params['pageToken'] = $pageToken; }
            $json = $this->api($accessToken)->get('https://www.googleapis.com/calendar/v3/users/me/calendarList', $params)->throw()->json();
            foreach (($json['items'] ?? []) as $item) {
                if (! empty($item['deleted'])) { continue; }
                $items[] = [
                    'external_id' => (string) $item['id'],
                    'name' => (string) ($item['summaryOverride'] ?? $item['summary'] ?? $item['id']),
                    'timezone' => $item['timeZone'] ?? null,
                    'access_role' => $item['accessRole'] ?? null,
                    'can_write' => in_array($item['accessRole'] ?? '', ['writer', 'owner'], true),
                    'is_primary' => (bool) ($item['primary'] ?? false),
                ];
            }
            $pageToken = $json['nextPageToken'] ?? null;
        } while ($pageToken);
        return $items;
    }

    public function busyIntervals(string $accessToken, array $calendars, CarbonImmutable $fromUtc, CarbonImmutable $toUtc): array
    {
        if ($calendars === []) { return []; }
        $json = $this->api($accessToken)->post('https://www.googleapis.com/calendar/v3/freeBusy', [
            'timeMin' => $fromUtc->toIso8601String(), 'timeMax' => $toUtc->toIso8601String(), 'timeZone' => 'UTC',
            'items' => array_map(fn (array $calendar): array => ['id' => $calendar['external_id']], $calendars),
        ])->throw()->json();
        $responses = (array) ($json['calendars'] ?? []);
        foreach ($calendars as $requestedCalendar) {
            $requestedId = (string) $requestedCalendar['external_id'];
            if (! array_key_exists($requestedId, $responses)) {
                throw new RuntimeException('Google Calendar omitted free/busy information for a configured calendar.');
            }
        }

        $busy = [];
        foreach ($responses as $calendar) {
            if (! empty($calendar['errors'])) {
                throw new RuntimeException('Google Calendar could not return free/busy information for a configured calendar.');
            }
            foreach (($calendar['busy'] ?? []) as $interval) {
                if (isset($interval['start'], $interval['end'])) { $busy[] = ['start' => $interval['start'], 'end' => $interval['end']]; }
            }
        }
        return $busy;
    }

    public function createEvent(string $accessToken, string $calendarId, array $event): array
    {
        $json = $this->api($accessToken)->post($this->eventCollectionUrl($calendarId), $event)->throw()->json();
        return ['id' => (string) $json['id'], 'etag' => $json['etag'] ?? null];
    }

    public function updateEvent(string $accessToken, string $calendarId, string $eventId, array $event): array
    {
        $json = $this->api($accessToken)->patch($this->eventCollectionUrl($calendarId).'/'.rawurlencode($eventId), $event)->throw()->json();
        return ['id' => (string) $json['id'], 'etag' => $json['etag'] ?? null];
    }

    public function deleteEvent(string $accessToken, string $calendarId, string $eventId): void
    {
        $response = $this->api($accessToken)->delete($this->eventCollectionUrl($calendarId).'/'.rawurlencode($eventId));
        if (! in_array($response->status(), [204, 404, 410], true)) { $response->throw(); }
    }

    private function eventCollectionUrl(string $calendarId): string
    {
        return 'https://www.googleapis.com/calendar/v3/calendars/'.rawurlencode($calendarId).'/events';
    }

    private function api(string $accessToken): PendingRequest
    {
        return Http::acceptJson()->withToken($accessToken)->timeout((int) config('calendars.http_timeout_seconds', 8));
    }
    private function form(): PendingRequest { return Http::asForm()->acceptJson()->timeout((int) config('calendars.http_timeout_seconds', 8)); }
    private function assertConfigured(array $config): void
    {
        if (empty($config['client_id']) || empty($config['client_secret']) || empty($config['redirect_uri'])) {
            throw new RuntimeException('Google Calendar OAuth is not configured.');
        }
    }
}
