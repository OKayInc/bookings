<?php

namespace Tests\Feature;

use App\Domain\Availability\AvailabilityScheduleService;
use App\Domain\Availability\AvailabilityService;
use App\Domain\Availability\BookingHoldService;
use App\Domain\Availability\CachedAvailabilityService;
use App\Domain\Availability\ResourceAvailabilityCache;
use App\Enums\AvailabilityScope;
use App\Models\Appointment;
use App\Models\AppointmentType;
use App\Models\BookingHold;
use App\Models\Organization;
use App\Models\Resource;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ResourceAvailabilityCacheIntegrationTest extends TestCase
{
    // RefreshDatabase wraps each test in a transaction, which deliberately disables this cache.
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();
        config(['resource-availability-cache.store' => 'array', 'resource-availability-cache.seconds' => 30,
            'configuration-cache.store' => 'array', 'cache.default' => 'array']);
        Cache::store('array')->flush();
        $this->travelTo(CarbonImmutable::parse('2026-09-28 08:00:00 UTC'));
        Http::preventStrayRequests();
    }

    public function test_container_uses_cache_and_a_repeat_resource_read_executes_no_sql(): void
    {
        [$type, $resource, $start] = $this->context();
        $service = app(AvailabilityService::class);
        $this->assertInstanceOf(CachedAvailabilityService::class, $service);
        DB::enableQueryLog();
        $this->assertTrue($service->isResourceAvailableAt($resource, $type, $start, $start->addHour()));
        $this->assertNotEmpty(DB::getQueryLog());
        DB::flushQueryLog();
        $this->assertTrue($service->isResourceAvailableAt($resource, $type, $start, $start->addHour()));
        $this->assertSame([], DB::getQueryLog());
        DB::disableQueryLog();
    }

    public function test_hold_creation_and_bulk_release_invalidate_shared_resource_results(): void
    {
        [$type, $resource, $start] = $this->context();
        $otherOrg = Organization::factory()->create(['timezone' => 'UTC']);
        $resource->organizations()->attach($otherOrg->getKey());
        $other = $this->type($otherOrg);
        $other->resources()->attach($resource->getKey(), ['is_required' => true]);
        $this->hours($otherOrg);
        $other->load('organization');
        $this->assertTrue($this->available($resource, $type, $start));
        $this->assertTrue($this->available($resource, $other, $start));
        $lease = app(BookingHoldService::class)->acquire($type, $start, 60, 'UTC', 10);
        $this->assertFalse($this->available($resource, $type, $start));
        $this->assertFalse($this->available($resource, $other, $start));
        // Exercise the central bulk builder, as used by release/cancellation/API code.
        BookingHold::query()->whereKey($lease->hold->getKey())->update(['status' => 'released']);
        $this->assertTrue($this->available($resource, $other, $start));
    }

    public function test_release_service_and_expiry_command_path_invalidate_negative_results(): void
    {
        [$type, $resource, $start] = $this->context();
        $service = app(BookingHoldService::class);
        $lease = $service->acquire($type, $start, 60, 'UTC', 10);
        $this->assertFalse($this->available($resource, $type, $start));
        $this->assertTrue($service->release($lease->token));
        $this->assertTrue($this->available($resource, $type, $start));
        $lease = $service->acquire($type, $start, 60, 'UTC', 10);
        $lease->hold->update(['expires_at_utc' => now('UTC')->addSeconds(5)]);
        $this->assertFalse($this->available($resource, $type, $start));
        $this->travel(6)->seconds();
        $this->assertSame(1, $service->expire());
        $this->assertTrue($this->available($resource, $type, $start));
    }

    public function test_allocation_pivot_attach_and_explicit_detach_invalidate_without_parent_save(): void
    {
        [$type, $resource, $start] = $this->context();
        $appointment = $this->appointment($type, $start);
        $this->assertTrue($this->available($resource, $type, $start));
        $appointment->resources()->attach($resource->getKey(), ['is_required' => true, 'quantity_reserved' => 1]);
        $this->assertFalse($this->available($resource, $type, $start));
        $appointment->resources()->detach($resource->getKey());
        $this->assertTrue($this->available($resource, $type, $start));
    }

    public function test_bulk_appointment_delete_captures_resources_before_cascades(): void
    {
        [$type, $resource, $start] = $this->context();
        $appointment = $this->appointment($type, $start);
        $appointment->resources()->attach($resource->getKey(), ['is_required' => true]);
        $this->assertFalse($this->available($resource, $type, $start));
        Appointment::query()->whereKey($appointment->getKey())->delete();
        $this->assertTrue($this->available($resource, $type, $start));
    }

    public function test_schedule_edits_invalidate_cached_results(): void
    {
        [$type, $resource, $start] = $this->context();
        $this->assertTrue($this->available($resource, $type, $start));
        app(AvailabilityScheduleService::class)->save($type->organization, AvailabilityScope::Resource,
            $resource, 'UTC', true, [['weekday' => 1, 'start_time' => '12:00', 'end_time' => '17:00']]);
        $this->assertFalse($this->available($resource, $type, $start));
    }

    public function test_rollback_does_not_publish_uncommitted_availability(): void
    {
        [$type, $resource, $start] = $this->context();
        $this->assertTrue($this->available($resource, $type, $start));
        DB::beginTransaction();
        try {
            $appointment = $this->appointment($type, $start);
            $appointment->resources()->attach($resource->getKey(), ['is_required' => true]);
            $this->assertFalse($this->available($resource, $type, $start));
        } finally {
            DB::rollBack();
        }
        $this->assertTrue($this->available($resource, $type, $start));
    }

    public function test_cached_true_never_authorizes_a_conflicting_hold(): void
    {
        [$type, $resource, $start] = $this->context();
        app(BookingHoldService::class)->acquire($type, $start, 60, 'UTC', 10);
        // Deliberately seed an incorrect browsing result; authoritative paths must ignore it.
        app(ResourceAvailabilityCache::class)->resource($resource, $type, $start, $start->addHour(), false, fn () => true);
        $this->assertTrue($this->available($resource, $type, $start));
        $this->assertFalse(app(AvailabilityService::class)->isResourceAvailableAt($resource, $type, $start, $start->addHour(), true));
        $this->assertFalse(DB::transaction(fn () => $this->available($resource, $type, $start)));
        $this->expectException(\RuntimeException::class);
        app(BookingHoldService::class)->acquire($type, $start, 60, 'UTC', 10);
    }

    public function test_cache_failure_does_not_assume_that_a_busy_resource_is_free(): void
    {
        [$type, $resource, $start] = $this->context();
        app(BookingHoldService::class)->acquire($type, $start, 60, 'UTC', 10);
        config(['resource-availability-cache.store' => 'deliberately_unconfigured_store']);
        $this->assertFalse($this->available($resource, $type, $start));
    }

    private function available(Resource $resource, AppointmentType $type, CarbonImmutable $start): bool
    {
        return app(AvailabilityService::class)->isResourceAvailableAt($resource, $type, $start, $start->addHour());
    }

    private function context(): array
    {
        $organization = Organization::factory()->create(['timezone' => 'UTC']);
        $type = $this->type($organization);
        $resource = Resource::create(['organization_id' => $organization->getKey(), 'type' => 'person',
            'name' => 'Resource', 'timezone' => 'UTC', 'is_active' => true]);
        $type->resources()->attach($resource->getKey(), ['is_required' => true]);
        $this->hours($organization);
        return [$type->fresh(['organization', 'resources']), $resource, CarbonImmutable::parse('2026-09-28 10:00:00 UTC')];
    }

    private function type(Organization $organization): AppointmentType
    {
        return AppointmentType::create(['organization_id' => $organization->getKey(), 'name' => 'Session',
            'slug' => 'session', 'visibility' => 'public', 'attendance_mode' => 'single', 'capacity' => 1,
            'duration_mode' => 'fixed', 'duration_unit' => 'minute', 'duration_value' => 60,
            'start_interval_minutes' => 60, 'buffer_before_minutes' => 0, 'buffer_after_minutes' => 0,
            'pricing_mode' => 'free', 'is_active' => true]);
    }

    private function hours(Organization $organization): void
    {
        app(AvailabilityScheduleService::class)->save($organization, AvailabilityScope::Organization,
            $organization, 'UTC', true, [['weekday' => 1, 'start_time' => '09:00', 'end_time' => '17:00']]);
    }

    private function appointment(AppointmentType $type, CarbonImmutable $start): Appointment
    {
        return Appointment::create(['organization_id' => $type->organization_id, 'appointment_type_id' => $type->getKey(),
            'starts_at_utc' => $start, 'ends_at_utc' => $start->addHour(),
            'blocked_starts_at_utc' => $start, 'blocked_ends_at_utc' => $start->addHour(),
            'scheduling_timezone' => 'UTC', 'duration_value' => 60, 'capacity' => 1, 'status' => 'scheduled']);
    }
}
