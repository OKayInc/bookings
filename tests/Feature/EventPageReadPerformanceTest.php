<?php

namespace Tests\Feature;

use App\Models\AppointmentType;
use App\Models\Organization;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class EventPageReadPerformanceTest extends TestCase
{
    // PageReadCache deliberately bypasses reads inside transactions.
    use DatabaseMigrations;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_event_page_reuses_the_current_event_and_reads_changes_on_the_next_request(): void
    {
        $type = $this->eventType();
        $first = $type->eventOccurrences()->create(['starts_at_utc' => '2026-11-10 15:00:00', 'timezone' => 'UTC', 'is_active' => true]);
        $second = $type->eventOccurrences()->create(['starts_at_utc' => '2026-11-11 15:00:00', 'timezone' => 'UTC', 'is_active' => true]);
        $url = route('public.appointment-types.show', [$type->organization->slug, $type->slug]);
        [$response, $queries] = $this->eventQueries(fn () => $this->get($url));
        $response->assertOk()->assertSee($first->starts_at_utc->toIso8601String(), false);
        $this->assertSame(1, $queries);

        $first->update(['is_active' => false]);
        [$response, $queries] = $this->eventQueries(fn () => $this->get($url));
        $response->assertOk()->assertSee($second->starts_at_utc->toIso8601String(), false)
            ->assertDontSee($first->starts_at_utc->toIso8601String(), false);
        $this->assertSame(1, $queries);
    }

    public function test_empty_and_past_event_fallbacks_are_reused_without_changing_selection(): void
    {
        $type = $this->eventType();
        $url = route('public.appointment-types.show', [$type->organization->slug, $type->slug]);
        [$response, $queries] = $this->eventQueries(fn () => $this->get($url));
        $response->assertOk()->assertSee('const eventStart = null;', false);
        $this->assertSame(2, $queries);

        $type->eventOccurrences()->create(['starts_at_utc' => '2026-10-01 15:00:00', 'timezone' => 'UTC', 'is_active' => true]);
        $last = $type->eventOccurrences()->create(['starts_at_utc' => '2026-10-02 15:00:00', 'timezone' => 'UTC', 'is_active' => true]);
        $type->eventOccurrences()->create(['starts_at_utc' => '2026-11-10 15:00:00', 'timezone' => 'UTC', 'is_active' => false]);
        [$response, $queries] = $this->eventQueries(fn () => $this->get($url));
        $response->assertOk()->assertSee($last->starts_at_utc->toIso8601String(), false);
        $this->assertSame(2, $queries);
    }

    public function test_event_reads_are_invalidated_by_writes_and_transactions_and_not_shared_between_types(): void
    {
        $type = $this->eventType();
        $event = $type->eventOccurrences()->create(['starts_at_utc' => '2026-11-10 15:00:00', 'timezone' => 'UTC', 'is_active' => true]);
        $other = $this->eventType();
        $otherEvent = $other->eventOccurrences()->create(['starts_at_utc' => '2026-11-12 15:00:00', 'timezone' => 'UTC', 'is_active' => true]);
        Route::get('/_event-read-test', function () use ($type, $event, $other, $otherEvent) {
            $this->assertSame($event->uuid, $type->currentEventOccurrence()->uuid);
            $this->assertSame($otherEvent->uuid, $other->currentEventOccurrence()->uuid);
            DB::table('event_occurrences')->where('id', $event->getKey())->update(['is_active' => false]);
            $this->assertNull($type->currentEventOccurrence());
            DB::beginTransaction();
            try {
                DB::table('event_occurrences')->where('id', $event->getKey())->update(['is_active' => true]);
                $this->assertSame($event->uuid, $type->currentEventOccurrence()->uuid);
            } finally {
                DB::rollBack();
            }
            $this->assertNull($type->currentEventOccurrence());

            return response('ok');
        })->middleware('web');
        $this->get('/_event-read-test')->assertOk();

        Route::post('/_event-read-test', function () use ($other) {
            $other->currentEventOccurrence();
            $other->currentEventOccurrence();

            return response('ok');
        })->middleware('web');
        [$response, $queries] = $this->eventQueries(fn () => $this->post('/_event-read-test'));
        $response->assertOk();
        $this->assertSame(2, $queries, 'POST reads must bypass page reuse.');
    }

    private function eventType(): AppointmentType
    {
        CarbonImmutable::setTestNow('2026-11-05 12:00:00 UTC');
        $organization = Organization::factory()->create(['timezone' => 'UTC']);

        return AppointmentType::create(['organization_id' => $organization->getKey(), 'name' => 'Evening show',
            'slug' => 'evening-show', 'visibility' => 'public', 'attendance_mode' => 'group', 'capacity' => 20,
            'ticketing_enabled' => true, 'show_start_offset_minutes' => 15, 'show_end_offset_minutes' => 60,
            'duration_mode' => 'fixed', 'duration_unit' => 'minute', 'duration_value' => 90,
            'start_interval_minutes' => 90, 'pricing_mode' => 'free', 'email_verification_mode' => 'none', 'is_active' => true]);
    }

    private function eventQueries(callable $read): array
    {
        DB::enableQueryLog();
        DB::flushQueryLog();
        try {
            $response = $read();
            $count = count(array_filter(DB::getQueryLog(), fn ($query) => str_contains($query['query'], 'from `event_occurrences`')));

            return [$response, $count];
        } finally {
            DB::disableQueryLog();
        }
    }
}
