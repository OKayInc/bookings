<?php

namespace Tests\Unit;

use App\Domain\Calendars\GoogleCalendarProvider;
use App\Domain\Calendars\MicrosoftCalendarProvider;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CalendarProviderTest extends TestCase
{
    public function test_google_freebusy_response_is_normalized(): void
    {
        Http::fake([
            'https://www.googleapis.com/calendar/v3/freeBusy' => Http::response([
                'calendars' => ['cal@example.test' => ['busy' => [[
                    'start' => '2026-08-26T14:00:00Z', 'end' => '2026-08-26T15:00:00Z',
                ]]]],
            ]),
        ]);

        $busy = app(GoogleCalendarProvider::class)->busyIntervals(
            'token', [['external_id' => 'cal@example.test']],
            CarbonImmutable::parse('2026-08-26T00:00:00Z'), CarbonImmutable::parse('2026-08-27T00:00:00Z'),
        );

        $this->assertSame([['start' => '2026-08-26T14:00:00Z', 'end' => '2026-08-26T15:00:00Z']], $busy);
    }

    public function test_microsoft_calendar_view_ignores_free_and_cancelled_events(): void
    {
        Http::fake([
            'https://graph.microsoft.com/v1.0/me/calendars/*/calendarView*' => Http::response([
                'value' => [
                    ['showAs' => 'busy', 'isCancelled' => false, 'start' => ['dateTime' => '2026-08-26T14:00:00'], 'end' => ['dateTime' => '2026-08-26T15:00:00']],
                    ['showAs' => 'free', 'isCancelled' => false, 'start' => ['dateTime' => '2026-08-26T16:00:00'], 'end' => ['dateTime' => '2026-08-26T17:00:00']],
                    ['showAs' => 'busy', 'isCancelled' => true, 'start' => ['dateTime' => '2026-08-26T18:00:00'], 'end' => ['dateTime' => '2026-08-26T19:00:00']],
                ],
            ]),
        ]);

        $busy = app(MicrosoftCalendarProvider::class)->busyIntervals(
            'token', [['external_id' => 'calendar-id']],
            CarbonImmutable::parse('2026-08-26T00:00:00Z'), CarbonImmutable::parse('2026-08-27T00:00:00Z'),
        );

        $this->assertCount(1, $busy);
        $this->assertSame('2026-08-26T14:00:00', $busy[0]['start']);
    }
    public function test_google_freebusy_missing_calendar_is_rejected(): void
    {
        Http::fake([
            'https://www.googleapis.com/calendar/v3/freeBusy' => Http::response(['calendars' => []]),
        ]);

        $this->expectException(\RuntimeException::class);
        app(GoogleCalendarProvider::class)->busyIntervals(
            'token', [['external_id' => 'required@example.test']],
            CarbonImmutable::parse('2026-08-26T00:00:00Z'), CarbonImmutable::parse('2026-08-27T00:00:00Z'),
        );
    }

    public function test_google_calendar_list_serializes_boolean_query_parameters_as_literals(): void
    {
        Http::fake([
            'https://www.googleapis.com/calendar/v3/users/me/calendarList*' => Http::response([
                'items' => [[
                    'id' => 'primary@example.test',
                    'summary' => 'Primary',
                    'accessRole' => 'owner',
                    'primary' => true,
                ]],
            ]),
        ]);

        $calendars = app(GoogleCalendarProvider::class)->listCalendars('token');

        $this->assertCount(1, $calendars);
        Http::assertSent(function ($request): bool {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

            return ($query['maxResults'] ?? null) === '250'
                && ($query['showHidden'] ?? null) === 'true';
        });
    }

}
