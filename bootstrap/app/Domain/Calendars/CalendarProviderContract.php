<?php

namespace App\Domain\Calendars;

use App\Enums\CalendarProvider;
use Carbon\CarbonImmutable;

interface CalendarProviderContract
{
    public function provider(): CalendarProvider;
    public function authorizationUrl(string $state): string;
    /** @return array<string,mixed> */
    public function exchangeAuthorizationCode(string $code): array;
    /** @return array<string,mixed> */
    public function refreshAccessToken(string $refreshToken): array;
    /** @return array{id:?string,name:?string,email:?string} */
    public function accountProfile(string $accessToken): array;
    /** @return list<array<string,mixed>> */
    public function listCalendars(string $accessToken): array;
    /** @param list<array<string,mixed>> $calendars @return list<array{start:string,end:string}> */
    public function busyIntervals(string $accessToken, array $calendars, CarbonImmutable $fromUtc, CarbonImmutable $toUtc): array;
    /** @param array<string,mixed> $event @return array{id:string,etag:?string} */
    public function createEvent(string $accessToken, string $calendarId, array $event): array;
    /** @param array<string,mixed> $event @return array{id:string,etag:?string} */
    public function updateEvent(string $accessToken, string $calendarId, string $eventId, array $event): array;
    public function deleteEvent(string $accessToken, string $calendarId, string $eventId): void;
}
