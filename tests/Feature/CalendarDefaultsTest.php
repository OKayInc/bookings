<?php

namespace Tests\Feature;

use App\Domain\Calendars\CalendarAvailabilityService;
use App\Domain\Calendars\CalendarManager;
use App\Domain\Calendars\CalendarSelectionService;
use App\Domain\Calendars\CalendarSyncService;
use App\Models\Appointment;
use App\Models\AppointmentType;
use App\Models\CalendarConnection;
use App\Models\ExternalCalendar;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\Resource;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class CalendarDefaultsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
    }

    public function test_unconfigured_types_check_owned_calendars_only_and_use_the_saved_write_target(): void
    {
        [$user, $org, $resource, $type, $connection] = $this->context();
        $first = $this->calendar($connection, 'owned-one');
        $second = $this->calendar($connection, 'owned-two');
        $this->calendar($connection, 'shared-read', false, false);
        $sharedWrite = $this->calendar($connection, 'shared-write', false);
        $this->calendar($connection, 'unknown', null);
        $this->signIn($user, $org);
        $this->put(route('calendar-connections.defaults.update', $resource), ['default_write_calendar' => $sharedWrite->uuid])
            ->assertSessionHasNoErrors()->assertRedirect();

        $selection = $this->selection($type, $resource);
        $this->assertEqualsCanonicalizing([$first->uuid, $second->uuid], $selection['check']->pluck('uuid')->all());
        $this->assertSame([$sharedWrite->uuid], $selection['write']->pluck('uuid')->all());
        $this->assertSame([], $selection['custom_resource_ids']);
    }

    public function test_saving_the_same_default_twice_keeps_it_and_switching_provider_clears_the_old_default(): void
    {
        [$user, $org, $resource, $type, $connection] = $this->context();
        $google = $this->calendar($connection, 'google-write');
        $microsoft = $this->calendar($this->connection($org, $resource, 'microsoft'), 'outlook-write');
        $this->signIn($user, $org);
        for ($i = 0; $i < 2; $i++) {
            $this->put(route('calendar-connections.defaults.update', $resource), ['default_write_calendar' => $google->uuid])
                ->assertSessionHasNoErrors();
            $this->assertTrue($google->fresh()->is_default_write);
        }
        $this->put(route('calendar-connections.defaults.update', $resource), ['default_write_calendar' => $microsoft->uuid])
            ->assertSessionHasNoErrors();
        $this->assertFalse($google->fresh()->is_default_write);
        $this->assertTrue($microsoft->fresh()->is_default_write);
        $this->put(route('calendar-connections.defaults.update', $resource), ['default_write_calendar' => ''])
            ->assertSessionHasNoErrors();
        $this->assertCount(0, $this->selection($type, $resource)['write']);
        $this->assertCount(2, $this->selection($type, $resource)['check']);
    }

    public function test_read_only_missing_inactive_revoked_and_other_resource_write_targets_are_rejected(): void
    {
        [$user, $org, $resource, $type, $connection] = $this->context();
        $good = $this->calendar($connection, 'good');
        $good->update(['is_default_write' => true]);
        $readOnly = $this->calendar($connection, 'read-only', true, false);
        $inactive = $this->calendar($connection, 'inactive');
        $inactive->update(['is_active' => false]);
        $revokedConnection = $this->connection($org, $resource, 'microsoft');
        $revoked = $this->calendar($revokedConnection, 'revoked');
        $revokedConnection->update(['status' => 'revoked']);
        $other = $this->resource($org, User::factory()->create(), 'Other member');
        $otherCalendar = $this->calendar($this->connection($org, $other), 'other-resource');
        $this->signIn($user, $org);
        foreach ([$readOnly->uuid, $inactive->uuid, $revoked->uuid, $otherCalendar->uuid, (string) Str::uuid(), 'invalid'] as $uuid) {
            $this->put(route('calendar-connections.defaults.update', $resource), ['default_write_calendar' => $uuid])
                ->assertSessionHasErrors('default_write_calendar');
            $this->assertTrue($good->fresh()->is_default_write);
        }
    }

    public function test_legacy_custom_settings_win_and_an_empty_override_does_not_fall_back_to_defaults(): void
    {
        [$user, $org, $resource, $type, $connection] = $this->context();
        $owned = $this->calendar($connection, 'owned');
        $owned->update(['is_default_write' => true]);
        $shared = $this->calendar($connection, 'shared', false);
        $type->externalCalendars()->attach($shared->getKey(), ['check_availability' => true, 'create_event' => false]);
        $type->load('externalCalendars'); // Intentionally retain a stale model graph.
        $this->assertSame([$shared->uuid], $this->selection($type, $resource)['check']->pluck('uuid')->all());
        $this->assertCount(0, $this->selection($type, $resource)['write']);
        $this->signIn($user, $org);
        $url = route('appointment-types.calendars.update', $type);
        $this->put($url, ['calendar_mode' => [$resource->uuid => 'custom']])->assertSessionHasNoErrors();
        $this->assertCount(0, $type->externalCalendars()->get());
        $this->assertCount(0, $this->selection($type, $resource)['check']);
        $this->assertCount(0, $this->selection($type, $resource)['write']);
        $this->put($url, ['calendar_mode' => [$resource->uuid => 'default']])->assertSessionHasNoErrors();
        $this->assertSame([$owned->uuid], $this->selection($type, $resource)['check']->pluck('uuid')->all());
        $this->assertSame([$owned->uuid], $this->selection($type, $resource)['write']->pluck('uuid')->all());
    }

    public function test_employee_can_customize_own_calendars_without_seeing_or_erasing_coworker_settings(): void
    {
        [$user, $org, $resource, $type, $connection] = $this->context('employee');
        $owned = $this->calendar($connection, 'my-calendar');
        $coworker = $this->resource($org, User::factory()->create(), 'Private Coworker');
        $type->resources()->attach($coworker->getKey(), ['is_required' => true, 'requirement_mode' => 'inherit']);
        $otherCalendar = $this->calendar($this->connection($org, $coworker), 'coworker-calendar');
        $type->externalCalendars()->attach($otherCalendar->getKey(), ['check_availability' => true, 'create_event' => true]);
        $this->signIn($user, $org);
        $this->get(route('calendar-connections.index'))->assertOk()
            ->assertSee('Default writing calendar')->assertSee('Customize calendars by appointment type')
            ->assertSee(route('appointment-types.calendars.edit', ['appointmentType' => $type, 'resource' => $resource->uuid]), false)
            ->assertDontSee('Private Coworker')->assertDontSee('coworker-calendar');
        $this->get(route('appointment-types.calendars.edit', $type))->assertOk()->assertDontSee('coworker-calendar');
        $this->put(route('appointment-types.calendars.update', $type), [
            'calendar_mode' => [$resource->uuid => 'custom'],
            'check_calendars' => [$owned->uuid], 'write_calendar' => [$resource->uuid => $owned->uuid],
        ])->assertSessionHasNoErrors();
        $this->assertTrue((bool) $type->externalCalendars()->findOrFail($otherCalendar->getKey())->pivot->create_event);
        $this->put(route('appointment-types.calendars.update', $type), ['calendar_mode' => [$resource->uuid => 'default']])
            ->assertSessionHasNoErrors();
        $this->assertTrue((bool) $type->externalCalendars()->findOrFail($otherCalendar->getKey())->pivot->create_event);
        $this->put(route('calendar-connections.defaults.update', $coworker), ['default_write_calendar' => $otherCalendar->uuid])->assertForbidden();
        $this->put(route('appointment-types.calendars.update', $type), ['calendar_mode' => [$coworker->uuid => 'default']])
            ->assertSessionHasErrors('calendar_mode');
        $this->get(route('appointment-types.calendars.edit', ['appointmentType' => $type, 'resource' => $coworker->uuid]))->assertNotFound();
    }

    public function test_shared_resource_connections_and_defaults_are_isolated_by_organization(): void
    {
        [$user, $org, $resource, $type, $connection] = $this->context();
        $local = $this->calendar($connection, 'local-calendar');
        $otherOrg = Organization::factory()->create();
        $otherOrg->resources()->attach($resource->getKey());
        $foreign = $this->calendar($this->connection($otherOrg, $resource), 'foreign-calendar');
        $foreign->update(['is_default_write' => true]);
        $this->signIn($user, $org);
        $this->get(route('appointment-types.calendars.edit', $type))->assertOk()->assertDontSee('foreign-calendar');
        $this->put(route('calendar-connections.defaults.update', $resource), ['default_write_calendar' => $foreign->uuid])
            ->assertSessionHasErrors('default_write_calendar');
        $this->put(route('appointment-types.calendars.update', $type), ['check_calendars' => [$foreign->uuid]])
            ->assertSessionHasErrors('check_calendars');
        $this->put(route('calendar-connections.defaults.update', $resource), ['default_write_calendar' => $local->uuid])
            ->assertSessionHasNoErrors();
        $this->assertTrue($foreign->fresh()->is_default_write);
        $this->assertSame([$local->uuid], $this->selection($type, $resource)['check']->pluck('uuid')->all());
        $this->assertSame([$local->uuid], $this->selection($type, $resource)['write']->pluck('uuid')->all());
    }

    public function test_inherited_availability_requests_owned_calendars_and_excludes_shared_calendars(): void
    {
        [, , $resource, $type, $connection] = $this->context();
        $this->calendar($connection, 'owned');
        $this->calendar($connection, 'shared', false);
        Http::fake(['https://www.googleapis.com/calendar/v3/freeBusy' => Http::response([
            'calendars' => ['owned' => ['busy' => [['start' => '2026-10-01T12:00:00Z', 'end' => '2026-10-01T13:00:00Z']]]],
        ])]);
        $intervals = app(CalendarAvailabilityService::class)->forResource($resource, $type,
            CarbonImmutable::parse('2026-10-01T00:00:00Z'), CarbonImmutable::parse('2026-10-02T00:00:00Z'), true);
        $this->assertCount(1, $intervals);
        Http::assertSent(fn (ClientRequest $request) => $request['items'] === [['id' => 'owned']]);
    }

    public function test_inherited_availability_fails_closed_when_an_owned_calendar_cannot_be_checked(): void
    {
        [, , $resource, $type, $connection] = $this->context();
        $this->calendar($connection, 'owned');
        Http::fake(['https://www.googleapis.com/calendar/v3/freeBusy' => Http::response([], 503)]);
        $from = CarbonImmutable::parse('2026-10-01T00:00:00Z');
        $to = $from->addDay();
        $intervals = app(CalendarAvailabilityService::class)->forResource($resource, $type, $from, $to, true);
        $this->assertCount(1, $intervals);
        $this->assertTrue($intervals[0]->start->equalTo($from));
        $this->assertTrue($intervals[0]->end->equalTo($to));
    }

    public function test_default_write_selection_controls_creation_reassignment_updates_and_cancellation(): void
    {
        [$user, $org, $resource, $type, $connection] = $this->context();
        $first = $this->calendar($connection, 'first-write');
        $second = $this->calendar($connection, 'second-write');
        $first->update(['is_default_write' => true]);
        $start = CarbonImmutable::now('UTC')->addDay();
        $appointment = Appointment::create([
            'organization_id' => $org->getKey(), 'appointment_type_id' => $type->getKey(),
            'starts_at_utc' => $start, 'ends_at_utc' => $start->addHour(),
            'blocked_starts_at_utc' => $start, 'blocked_ends_at_utc' => $start->addHour(),
            'scheduling_timezone' => 'America/Toronto', 'duration_value' => 60, 'capacity' => 1, 'status' => 'scheduled',
        ]);
        $appointment->resources()->attach($resource->getKey(), ['is_required' => true]);
        Http::fake(fn (ClientRequest $request) => $request->method() === 'DELETE'
            ? Http::response('', 204) : Http::response(['id' => 'event-'.md5($request->url())]));
        $sync = app(CalendarSyncService::class);
        $sync->syncAppointment($appointment->fresh());
        $this->assertSame($first->getKey(), $appointment->externalEvents()->firstOrFail()->external_calendar_id);
        $this->signIn($user, $org);
        $this->put(route('calendar-connections.defaults.update', $resource), ['default_write_calendar' => $second->uuid])
            ->assertSessionHasNoErrors();
        $sync->syncAppointment($appointment->fresh());
        $sync->syncAppointment($appointment->fresh());
        $this->assertSame(1, $appointment->externalEvents()->count());
        $this->assertSame($second->getKey(), $appointment->externalEvents()->firstOrFail()->external_calendar_id);
        Http::assertSent(fn (ClientRequest $request) => $request->method() === 'PATCH');
        $appointment->update(['status' => 'cancelled']);
        $sync->syncAppointment($appointment->fresh());
        $this->assertSame(0, $appointment->externalEvents()->count());
    }

    public function test_google_refresh_uses_data_ownership_and_preserves_the_default_choice(): void
    {
        [, , $resource, $type, $connection] = $this->context();
        $primary = $this->calendar($connection, 'primary');
        $primary->update(['is_default_write' => true]);
        Http::fake([
            'https://www.googleapis.com/oauth2/v3/userinfo' => Http::response(['sub' => 'account', 'email' => 'member@example.test']),
            'https://www.googleapis.com/calendar/v3/users/me/calendarList*' => Http::response(['items' => [
                ['id' => 'primary', 'primary' => true, 'accessRole' => 'owner'],
                ['id' => 'own-secondary', 'dataOwner' => 'MEMBER@example.test', 'accessRole' => 'owner'],
                ['id' => 'shared-writer', 'dataOwner' => 'other@example.test', 'accessRole' => 'writer'],
                ['id' => 'shared-manager', 'dataOwner' => 'other@example.test', 'accessRole' => 'owner'],
                ['id' => 'unknown-owner', 'accessRole' => 'owner'],
            ]]),
        ]);
        app(CalendarManager::class)->refreshCalendars($connection);
        $this->assertTrue($primary->fresh()->is_default_write);
        $selection = $this->selection($type, $resource);
        $this->assertEqualsCanonicalizing(['primary', 'own-secondary'], $selection['check']->pluck('external_id')->all());
        $this->assertSame(['primary'], $selection['write']->pluck('external_id')->all());
        $this->assertNull($connection->calendars()->where('external_id', 'unknown-owner')->firstOrFail()->is_owned);
        $primary->update(['is_active' => false]);
        $this->assertCount(0, $this->selection($type, $resource)['write']);
    }

    public function test_microsoft_refresh_distinguishes_owned_and_editable_shared_calendars(): void
    {
        [, $org, $resource, $type] = $this->context();
        $connection = $this->connection($org, $resource, 'microsoft');
        Http::fake([
            'https://graph.microsoft.com/v1.0/me?*' => Http::response([
                'id' => 'account', 'mail' => 'member@example.test', 'userPrincipalName' => 'alias@example.test',
            ]),
            'https://graph.microsoft.com/v1.0/me/calendars*' => Http::response(['value' => [
                ['id' => 'primary', 'isDefaultCalendar' => true, 'canEdit' => true],
                ['id' => 'own-secondary', 'owner' => ['address' => 'ALIAS@example.test'], 'canEdit' => true],
                ['id' => 'shared', 'owner' => ['address' => 'other@example.test'], 'canEdit' => true],
            ]]),
        ]);
        app(CalendarManager::class)->refreshCalendars($connection);
        $this->assertEqualsCanonicalizing(['primary', 'own-secondary'], $this->selection($type, $resource)['check']->pluck('external_id')->all());
        $this->assertCount(0, $this->selection($type, $resource)['write']);
    }

    public function test_google_batches_more_than_fifty_default_calendars_without_dropping_any(): void
    {
        $ids = array_map(fn ($i) => 'calendar-'.$i, range(1, 51));
        Http::fake(['https://www.googleapis.com/calendar/v3/freeBusy' => function (ClientRequest $request) {
            $this->assertLessThanOrEqual(50, count($request['items']));
            $calendars = [];
            foreach ($request['items'] as $item) { $calendars[$item['id']] = ['busy' => []]; }
            return Http::response(['calendars' => $calendars]);
        }]);
        $from = CarbonImmutable::parse('2026-10-01T00:00:00Z');
        $result = app(\App\Domain\Calendars\GoogleCalendarProvider::class)->busyIntervals('test-token',
            array_map(fn ($id) => ['external_id' => $id], $ids), $from, $from->addDay());
        $this->assertSame([], $result);
        Http::assertSentCount(2);
        $sent = Http::recorded()->flatMap(fn ($pair) => array_column($pair[0]['items'], 'id'))->all();
        $this->assertSame($ids, $sent);
    }

    private function selection(AppointmentType $type, Resource $resource): array
    {
        return app(CalendarSelectionService::class)->forType($type, [$resource->getKey()]);
    }

    private function signIn(User $user, Organization $organization): void
    {
        $this->actingAs($user)->withSession(['active_organization_uuid' => $organization->uuid]);
    }

    private function context(string $role = 'owner'): array
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create(['timezone' => 'America/Toronto']);
        OrganizationMembership::create([
            'organization_id' => $organization->getKey(), 'person_id' => $user->person_id,
            'role' => $role, 'status' => 'active',
        ]);
        $resource = $this->resource($organization, $user, 'Member calendars');
        $type = AppointmentType::create([
            'organization_id' => $organization->getKey(), 'name' => 'Default calendar session', 'slug' => 'default-calendar-session',
            'visibility' => 'public', 'attendance_mode' => 'single', 'capacity' => 1,
            'duration_mode' => 'fixed', 'duration_unit' => 'minute', 'duration_value' => 60,
            'start_interval_minutes' => 60, 'buffer_before_minutes' => 0, 'buffer_after_minutes' => 0,
            'pricing_mode' => 'free', 'is_active' => true,
        ]);
        $type->resources()->attach($resource->getKey(), ['is_required' => true, 'requirement_mode' => 'inherit']);
        return [$user, $organization, $resource, $type, $this->connection($organization, $resource)];
    }

    private function resource(Organization $organization, User $user, string $name): Resource
    {
        return Resource::create([
            'organization_id' => $organization->getKey(), 'person_id' => $user->person_id,
            'type' => 'person', 'name' => $name, 'timezone' => 'America/Toronto',
            'is_active' => true, 'is_required_by_default' => true,
        ]);
    }

    private function connection(Organization $organization, Resource $resource, string $provider = 'google'): CalendarConnection
    {
        return CalendarConnection::create([
            'organization_id' => $organization->getKey(), 'resource_id' => $resource->getKey(), 'provider' => $provider,
            'access_token' => 'test-token', 'refresh_token' => 'test-refresh',
            'token_expires_at_utc' => now('UTC')->addHour(), 'status' => 'active',
        ]);
    }

    private function calendar(CalendarConnection $connection, string $id, ?bool $owned = true, bool $write = true): ExternalCalendar
    {
        return ExternalCalendar::create([
            'calendar_connection_id' => $connection->getKey(), 'external_id' => $id,
            'external_id_hash' => hash('sha256', $id, true), 'name' => $id,
            'can_write' => $write, 'is_active' => true, 'is_primary' => false, 'is_owned' => $owned,
        ]);
    }
}
