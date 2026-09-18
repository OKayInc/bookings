<?php

namespace App\Domain\Calendars;

use App\Enums\CalendarProvider;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class MicrosoftCalendarProvider implements CalendarProviderContract
{
    public function provider(): CalendarProvider { return CalendarProvider::Microsoft; }

    public function authorizationUrl(string $state): string
    {
        $c = config('calendars.microsoft'); $this->assertConfigured($c);
        return $this->authority().'/oauth2/v2.0/authorize?'.http_build_query([
            'client_id' => $c['client_id'], 'response_type' => 'code', 'redirect_uri' => $c['redirect_uri'],
            'response_mode' => 'query', 'scope' => implode(' ', $c['scopes']), 'state' => $state,
        ], '', '&', PHP_QUERY_RFC3986);
    }

    public function exchangeAuthorizationCode(string $code): array
    {
        $c = config('calendars.microsoft'); $this->assertConfigured($c);
        return $this->form()->post($this->authority().'/oauth2/v2.0/token', [
            'client_id' => $c['client_id'], 'client_secret' => $c['client_secret'], 'code' => $code,
            'redirect_uri' => $c['redirect_uri'], 'grant_type' => 'authorization_code', 'scope' => implode(' ', $c['scopes']),
        ])->throw()->json();
    }

    public function refreshAccessToken(string $refreshToken): array
    {
        $c = config('calendars.microsoft'); $this->assertConfigured($c);
        return $this->form()->post($this->authority().'/oauth2/v2.0/token', [
            'client_id' => $c['client_id'], 'client_secret' => $c['client_secret'], 'refresh_token' => $refreshToken,
            'grant_type' => 'refresh_token', 'scope' => implode(' ', $c['scopes']),
        ])->throw()->json();
    }

    public function accountProfile(string $accessToken): array
    {
        $json = $this->api($accessToken)->get('https://graph.microsoft.com/v1.0/me', ['$select' => 'id,displayName,mail,userPrincipalName'])->throw()->json();
        return ['id' => $json['id'] ?? null, 'name' => $json['displayName'] ?? null, 'email' => $json['mail'] ?? ($json['userPrincipalName'] ?? null)];
    }

    public function listCalendars(string $accessToken): array
    {
        $items = []; $url = 'https://graph.microsoft.com/v1.0/me/calendars?$top=100';
        do {
            $json = $this->api($accessToken)->get($url)->throw()->json();
            foreach (($json['value'] ?? []) as $item) {
                $items[] = [
                    'external_id' => (string) $item['id'], 'name' => (string) ($item['name'] ?? 'Calendar'),
                    'timezone' => null, 'access_role' => ! empty($item['canEdit']) ? 'writer' : 'reader',
                    'can_write' => (bool) ($item['canEdit'] ?? false), 'is_primary' => false,
                ];
            }
            $url = $json['@odata.nextLink'] ?? null;
        } while ($url);
        return $items;
    }

    public function busyIntervals(string $accessToken, array $calendars, CarbonImmutable $fromUtc, CarbonImmutable $toUtc): array
    {
        $busy = [];
        foreach ($calendars as $calendar) {
            $url = 'https://graph.microsoft.com/v1.0/me/calendars/'.rawurlencode((string) $calendar['external_id']).'/calendarView';
            $params = [
                'startDateTime' => $fromUtc->toIso8601String(), 'endDateTime' => $toUtc->toIso8601String(),
                '$select' => 'start,end,showAs,isCancelled', '$top' => 200,
            ];
            do {
                $request = $this->api($accessToken)->withHeaders(['Prefer' => 'outlook.timezone="UTC"']);
                $json = str_starts_with($url, 'https://')
                    ? $request->get($url, $params)->throw()->json()
                    : [];
                $params = [];
                foreach (($json['value'] ?? []) as $event) {
                    if (! empty($event['isCancelled']) || ($event['showAs'] ?? 'busy') === 'free') { continue; }
                    $start = $event['start']['dateTime'] ?? null; $end = $event['end']['dateTime'] ?? null;
                    if ($start && $end) { $busy[] = ['start' => $start, 'end' => $end]; }
                }
                $url = $json['@odata.nextLink'] ?? null;
            } while ($url);
        }
        return $busy;
    }

    public function createEvent(string $accessToken, string $calendarId, array $event): array
    {
        $json = $this->api($accessToken)->post($this->eventCollectionUrl($calendarId), $event)->throw()->json();
        return ['id' => (string) $json['id'], 'etag' => $json['@odata.etag'] ?? null];
    }

    public function updateEvent(string $accessToken, string $calendarId, string $eventId, array $event): array
    {
        $json = $this->api($accessToken)->patch($this->eventCollectionUrl($calendarId).'/'.rawurlencode($eventId), $event)->throw()->json();
        return ['id' => (string) ($json['id'] ?? $eventId), 'etag' => $json['@odata.etag'] ?? null];
    }

    public function deleteEvent(string $accessToken, string $calendarId, string $eventId): void
    {
        $response = $this->api($accessToken)->delete($this->eventCollectionUrl($calendarId).'/'.rawurlencode($eventId));
        if (! in_array($response->status(), [204, 404, 410], true)) { $response->throw(); }
    }

    private function eventCollectionUrl(string $calendarId): string
    {
        return 'https://graph.microsoft.com/v1.0/me/calendars/'.rawurlencode($calendarId).'/events';
    }
    private function authority(): string
    {
        return 'https://login.microsoftonline.com/'.rawurlencode((string) config('calendars.microsoft.tenant', 'common'));
    }
    private function api(string $token): PendingRequest { return Http::acceptJson()->withToken($token)->timeout((int) config('calendars.http_timeout_seconds', 8)); }
    private function form(): PendingRequest { return Http::asForm()->acceptJson()->timeout((int) config('calendars.http_timeout_seconds', 8)); }
    private function assertConfigured(array $c): void
    {
        if (empty($c['client_id']) || empty($c['client_secret']) || empty($c['redirect_uri'])) { throw new RuntimeException('Microsoft Calendar OAuth is not configured.'); }
    }
}
