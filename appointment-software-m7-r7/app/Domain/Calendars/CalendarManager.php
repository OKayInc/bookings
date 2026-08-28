<?php

namespace App\Domain\Calendars;

use App\Enums\CalendarConnectionStatus;
use App\Enums\CalendarProvider;
use App\Models\CalendarConnection;
use App\Models\ExternalCalendar;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use RuntimeException;
use Throwable;

class CalendarManager
{
    public function __construct(
        private readonly GoogleCalendarProvider $google,
        private readonly MicrosoftCalendarProvider $microsoft,
    ) {}

    public function provider(CalendarProvider|string $provider): CalendarProviderContract
    {
        $provider = $provider instanceof CalendarProvider ? $provider : CalendarProvider::from($provider);
        return match ($provider) { CalendarProvider::Google => $this->google, CalendarProvider::Microsoft => $this->microsoft };
    }

    public function accessToken(CalendarConnection $connection): string
    {
        if ($connection->status === CalendarConnectionStatus::Revoked) { throw new RuntimeException('Calendar connection is revoked.'); }
        if ($connection->token_expires_at_utc === null || $connection->token_expires_at_utc->gt(now('UTC')->addMinutes(2))) {
            return (string) $connection->access_token;
        }
        if (! $connection->refresh_token) { throw new RuntimeException('Calendar connection has expired and has no refresh token.'); }
        try {
            $tokens = $this->provider($connection->provider)->refreshAccessToken((string) $connection->refresh_token);
            $connection->update([
                'access_token' => $tokens['access_token'],
                'refresh_token' => $tokens['refresh_token'] ?? $connection->refresh_token,
                'token_expires_at_utc' => isset($tokens['expires_in']) ? now('UTC')->addSeconds((int) $tokens['expires_in']) : null,
                'scopes' => $tokens['scope'] ?? $connection->scopes,
                'status' => CalendarConnectionStatus::Active->value, 'last_error' => null,
            ]);
            return (string) $connection->fresh()->access_token;
        } catch (Throwable $e) {
            $connection->update(['status' => CalendarConnectionStatus::Error->value, 'last_error' => $e->getMessage()]);
            throw $e;
        }
    }

    public function refreshCalendars(CalendarConnection $connection): Collection
    {
        $provider = $this->provider($connection->provider); $token = $this->accessToken($connection);
        $profile = $provider->accountProfile($token); $remote = $provider->listCalendars($token); $seen = [];
        foreach ($remote as $item) {
            $hash = hash('sha256', (string) $item['external_id'], true); $seen[] = $hash;
            ExternalCalendar::query()->updateOrCreate(
                ['calendar_connection_id' => $connection->getKey(), 'external_id_hash' => $hash],
                [
                    'external_id' => $item['external_id'], 'name' => $item['name'], 'timezone' => $item['timezone'] ?? null,
                    'access_role' => $item['access_role'] ?? null, 'can_write' => (bool) ($item['can_write'] ?? false),
                    'is_primary' => (bool) ($item['is_primary'] ?? false), 'is_active' => true, 'last_seen_at_utc' => now('UTC'),
                ],
            );
        }
        if ($seen === []) {
            $connection->calendars()->update(['is_active' => false]);
        } else {
            $connection->calendars()->whereNotIn('external_id_hash', $seen)->update(['is_active' => false]);
        }
        $connection->update([
            'external_account_id' => $profile['id'] ?? null,
            'external_account_name' => $profile['email'] ?? $profile['name'] ?? null,
            'status' => CalendarConnectionStatus::Active->value, 'last_error' => null, 'last_refreshed_at_utc' => now('UTC'),
        ]);
        return $connection->calendars()->orderByDesc('is_primary')->orderBy('name')->get();
    }

    /** @param Collection<int,ExternalCalendar> $calendars @return list<array{start:string,end:string}> */
    public function busyIntervals(Collection $calendars, CarbonImmutable $fromUtc, CarbonImmutable $toUtc, bool $fresh = false): array
    {
        $all = [];
        foreach ($calendars->groupBy('calendar_connection_id') as $group) {
            /** @var ExternalCalendar $first */ $first = $group->first();
            $connection = $first->connection;
            $cacheKey = 'calendar_busy:'.$connection->uuid.':'.hash('sha256', $group->pluck('uuid')->sort()->implode('|').'|'.$fromUtc->format('YmdHi').'|'.$toUtc->format('YmdHi'));
            try {
                $loader = function () use ($connection, $group, $fromUtc, $toUtc): array {
                    $token = $this->accessToken($connection);
                    return $this->provider($connection->provider)->busyIntervals($token, $group->map(fn (ExternalCalendar $c) => ['external_id' => $c->external_id])->values()->all(), $fromUtc, $toUtc);
                };
                $items = $fresh
                    ? $loader()
                    : Cache::remember($cacheKey, now()->addSeconds((int) config('calendars.busy_cache_seconds', 30)), $loader);
                array_push($all, ...$items);
            } catch (Throwable $e) {
                $connection->update(['status' => CalendarConnectionStatus::Error->value, 'last_error' => $e->getMessage()]);
                // Fail closed: an explicitly configured required external calendar that cannot be checked blocks the range.
                $all[] = ['start' => $fromUtc->toIso8601String(), 'end' => $toUtc->toIso8601String()];
            }
        }
        return $all;
    }

    public function forgetBusyCache(CalendarConnection $connection): void
    {
        // Memcached does not support prefix deletion portably. Busy entries are intentionally short lived.
    }
}
