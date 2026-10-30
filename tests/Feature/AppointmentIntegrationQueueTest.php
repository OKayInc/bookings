<?php

namespace Tests\Feature;

use App\Domain\Calendars\AppointmentIntegrationQueue;
use App\Domain\Calendars\CalendarSyncService;
use App\Domain\Conferences\ConferenceMeetingService;
use App\Jobs\SyncAppointmentIntegrations;
use App\Models\Appointment;
use App\Models\AppointmentType;
use App\Models\CalendarConnection;
use App\Models\ExternalCalendar;
use App\Models\Organization;
use App\Models\Resource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class AppointmentIntegrationQueueTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
    }

    public function test_unchanged_event_does_not_write_but_edits_and_force_do(): void
    {
        $appointment = $this->appointment();
        Http::fake(['https://www.googleapis.com/calendar/v3/calendars/*' => Http::response(['id' => 'event-1'])]);
        $sync = app(CalendarSyncService::class);
        $sync->syncAppointment($appointment->fresh());
        $sync->syncAppointment($appointment->fresh());
        Http::assertSentCount(1);

        $appointment->update(['event_location' => 'New venue']);
        $sync->syncAppointment($appointment->fresh());
        Http::assertSentCount(2);
        Http::assertSent(fn ($request) => $request->method() === 'PATCH' && $request['location'] === 'New venue');
        $sync->syncAppointment($appointment->fresh(), force: true);
        Http::assertSentCount(3);
    }

    public function test_failure_retains_pending_work_and_successful_retry_clears_it(): void
    {
        $appointment = $this->appointment();
        Http::fake(['https://www.googleapis.com/calendar/v3/calendars/*' => Http::sequence()
            ->push(['error' => 'Unavailable'], 503)->push(['id' => 'event-1'])]);
        try {
            $this->runJob($appointment);
            $this->fail('The provider failure should be retried.');
        } catch (\Illuminate\Http\Client\RequestException) {
            $request = DB::table('appointment_sync_requests')->where('appointment_id', $appointment->getKey())->first();
            $this->assertSame(1, $request->attempts);
            $this->assertNotNull($request->last_error);
        }
        $this->runJob($appointment);
        $this->assertDatabaseCount('appointment_sync_requests', 0);
        $this->assertDatabaseCount('appointment_external_events', 1);
    }

    public function test_cancellation_deletion_failure_is_retried(): void
    {
        $appointment = $this->appointment();
        Http::fake(['https://www.googleapis.com/calendar/v3/calendars/*' => Http::sequence()
            ->push(['id' => 'event-1'])->push([], 503)->push([], 204)]);
        $this->runJob($appointment);
        $appointment->update(['status' => 'cancelled']);
        try {
            $this->runJob($appointment);
            $this->fail('Failed calendar deletion must remain pending.');
        } catch (\Illuminate\Http\Client\RequestException) {
            $this->assertDatabaseCount('appointment_sync_requests', 1);
            $this->assertDatabaseCount('appointment_external_events', 1);
        }
        $this->runJob($appointment);
        $this->assertDatabaseCount('appointment_sync_requests', 0);
        $this->assertDatabaseCount('appointment_external_events', 0);
    }

    public function test_meeting_is_ready_before_calendar_write_and_is_reused_on_retry(): void
    {
        $appointment = $this->appointment();
        $appointment->update(['meeting_provider' => 'jitsi', 'meeting_status' => 'pending']);
        Http::fake(['https://www.googleapis.com/calendar/v3/calendars/*' => Http::sequence()
            ->push([], 503)->push(['id' => 'event-1'])]);
        try {
            $this->runJob($appointment);
            $this->fail('The calendar write should fail.');
        } catch (\Illuminate\Http\Client\RequestException) {
            $url = $appointment->fresh()->meeting_join_url;
            $this->assertNotEmpty($url);
            $this->assertSame('ready', $appointment->fresh()->meeting_status);
        }
        $this->runJob($appointment);
        $this->assertSame($url, $appointment->fresh()->meeting_join_url);
        Http::assertSent(fn ($request) => $request['location'] === $url && str_contains($request['description'], $url));
        $this->assertDatabaseCount('appointment_sync_requests', 0);
    }

    public function test_real_redis_dispatch_waits_for_commit_and_duplicate_work_is_coalesced(): void
    {
        if (! getenv('RUN_REDIS_TESTS')) {
            $this->markTestSkipped('Set RUN_REDIS_TESTS=1 and configure a dedicated test Redis instance.');
        }
        $queueName = 'integration-test-'.bin2hex(random_bytes(8));
        config(['queue.default' => 'redis', 'integrations.lock_store' => 'redis', 'integrations.queue' => $queueName]);
        $appointment = $this->appointment();
        $redis = app('queue')->connection('redis');
        $queue = app(AppointmentIntegrationQueue::class);
        Http::fake(['https://www.googleapis.com/calendar/v3/calendars/*' => Http::response(['id' => 'event-1'])]);
        try {
            DB::transaction(function () use ($appointment, $queue, $redis, $queueName): void {
                $queue->request($appointment);
                $this->assertSame(0, $redis->size($queueName));
            });
            $this->assertSame(1, $redis->size($queueName));
            $queue->dispatch($appointment->uuid);
            $this->assertSame(1, $redis->size($queueName));
            $job = $redis->pop($queueName);
            $this->assertNotNull($job);
            $job->fire();
            $job->delete();
            $this->assertDatabaseCount('appointment_sync_requests', 0);
            $this->assertDatabaseCount('appointment_external_events', 1);
            Http::assertSentCount(1);
            DB::beginTransaction();
            $queue->request($appointment);
            DB::rollBack();
            $this->assertSame(0, $redis->size($queueName));
            $this->assertDatabaseCount('appointment_sync_requests', 0);
        } finally {
            $redis->clear($queueName);
        }
    }

    public function test_edit_during_provider_request_is_not_lost(): void
    {
        $appointment = $this->appointment();
        $first = true;
        Http::fake(function () use ($appointment, &$first) {
            if ($first) {
                $first = false;
                $appointment->update(['event_location' => 'Changed during sync']);
            }
            return Http::response(['id' => 'event-1']);
        });
        $this->runJob($appointment);
        $this->assertDatabaseCount('appointment_sync_requests', 1);
        $this->runJob($appointment);
        $this->assertDatabaseCount('appointment_sync_requests', 0);
        Http::assertSent(fn ($request) => $request->method() === 'PATCH' && $request['location'] === 'Changed during sync');
    }

    public function test_rollback_does_not_dispatch_or_retain_sync_request(): void
    {
        Bus::fake();
        $appointment = $this->appointment();
        DB::table('appointment_sync_requests')->delete();
        DB::beginTransaction();
        app(AppointmentIntegrationQueue::class)->request($appointment);
        Bus::assertNothingDispatched();
        DB::rollBack();
        Bus::assertNothingDispatched();
        $this->assertDatabaseCount('appointment_sync_requests', 0);
    }

    public function test_broker_failure_does_not_lose_the_database_request(): void
    {
        $appointment = $this->appointment();
        Bus::shouldReceive('dispatch')->once()->andThrow(new RuntimeException('Redis unavailable'));
        app(AppointmentIntegrationQueue::class)->dispatch($appointment->uuid);
        $this->assertDatabaseCount('appointment_sync_requests', 1);
        Http::assertNothingSent();
    }

    public function test_normal_scheduler_dispatches_pending_work_only(): void
    {
        Bus::fake();
        $appointment = $this->appointment();
        DB::table('appointment_sync_requests')->delete();
        $this->artisan('appointments:sync-calendars')->assertSuccessful();
        Bus::assertNothingDispatched();
        $appointment->update(['event_location' => 'Changed venue']);
        $this->artisan('appointments:sync-calendars')->assertSuccessful();
        Bus::assertDispatched(SyncAppointmentIntegrations::class, fn ($job) => $job->appointmentUuid === $appointment->uuid);
    }

    private function runJob(Appointment $appointment): void
    {
        (new SyncAppointmentIntegrations($appointment->uuid))->handle(
            app(ConferenceMeetingService::class), app(CalendarSyncService::class),
        );
    }

    private function appointment(): Appointment
    {
        $organization = Organization::factory()->create();
        $type = AppointmentType::create([
            'organization_id' => $organization->getKey(), 'name' => 'Session', 'slug' => 'session',
            'visibility' => 'public', 'attendance_mode' => 'single', 'capacity' => 1,
            'duration_mode' => 'fixed', 'duration_unit' => 'minute', 'duration_value' => 60,
            'pricing_mode' => 'free', 'is_active' => true,
        ]);
        $resource = Resource::create(['organization_id' => $organization->getKey(), 'type' => 'person',
            'name' => 'Host', 'is_active' => true, 'is_required_by_default' => true]);
        $type->resources()->attach($resource->getKey(), ['is_required' => true]);
        $connection = CalendarConnection::create([
            'organization_id' => $organization->getKey(), 'resource_id' => $resource->getKey(), 'provider' => 'google',
            'access_token' => 'token', 'refresh_token' => 'refresh', 'token_expires_at_utc' => now('UTC')->addHour(), 'status' => 'active',
        ]);
        $calendar = ExternalCalendar::create([
            'calendar_connection_id' => $connection->getKey(), 'external_id' => 'host@example.test',
            'external_id_hash' => hash('sha256', 'host@example.test', true), 'name' => 'Calendar',
            'can_write' => true, 'is_primary' => true, 'is_active' => true,
        ]);
        $type->externalCalendars()->attach($calendar->getKey(), ['check_availability' => true, 'create_event' => true]);
        $start = now('UTC')->addDay();
        $appointment = Appointment::create([
            'organization_id' => $organization->getKey(), 'appointment_type_id' => $type->getKey(),
            'starts_at_utc' => $start, 'ends_at_utc' => $start->copy()->addHour(),
            'blocked_starts_at_utc' => $start, 'blocked_ends_at_utc' => $start->copy()->addHour(),
            'scheduling_timezone' => 'UTC', 'duration_value' => 60, 'capacity' => 1, 'status' => 'scheduled',
        ]);
        $appointment->resources()->attach($resource->getKey(), ['is_required' => true]);
        return $appointment;
    }
}
