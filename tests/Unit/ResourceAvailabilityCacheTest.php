<?php

namespace Tests\Unit;

use App\Domain\Availability\ResourceAvailabilityCache;
use App\Models\AppointmentType;
use App\Models\Organization;
use App\Models\Resource;
use Carbon\CarbonImmutable;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\CacheManager;
use Illuminate\Cache\Repository as CacheRepository;
use Illuminate\Config\Repository as ConfigRepository;
use Illuminate\Container\Container;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Database\Connection;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Facade;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** No database or Redis server is needed by these cache-contract tests. */
class ResourceAvailabilityCacheTest extends TestCase
{
    private ResourceAvailabilityCache $cache;
    private CacheRepository $store;
    private Container $container;
    private Resource $resource;
    private AppointmentType $type;
    private CarbonImmutable $start;
    private CarbonImmutable $end;
    private int $transactionLevel = 0;
    private array $commits = [];
    private bool $cacheOutage = false;

    protected function setUp(): void
    {
        parent::setUp();
        Model::clearBootedModels();
        Model::unsetEventDispatcher();
        $this->container = new Container();
        Container::setInstance($this->container);
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication($this->container);
        $this->container->instance('config', new ConfigRepository([
            'resource-availability-cache' => ['store' => 'array', 'seconds' => 30],
            'cache' => ['default' => 'array'],
            'calendars' => ['busy_cache_seconds' => 30],
        ]));
        $this->store = new CacheRepository(new ArrayStore());
        $manager = Mockery::mock(CacheManager::class);
        $manager->shouldReceive('store')->andReturnUsing(function () {
            if ($this->cacheOutage) {
                throw new \RuntimeException('Redis unavailable');
            }
            return $this->store;
        });
        $this->container->instance('cache', $manager);
        $connection = Mockery::mock(Connection::class);
        $connection->shouldReceive('transactionLevel')->andReturnUsing(fn () => $this->transactionLevel);
        $connection->shouldReceive('afterCommit')->andReturnUsing(function (callable $callback): void {
            $this->commits[] = $callback;
        });
        $db = Mockery::mock(DatabaseManager::class);
        $db->shouldReceive('transactionLevel')->andReturnUsing(fn () => $this->transactionLevel);
        $db->shouldReceive('connection')->andReturn($connection);
        $this->container->instance('db', $db);
        $handler = Mockery::mock(ExceptionHandler::class);
        $handler->shouldReceive('report')->zeroOrMoreTimes();
        $this->container->instance(ExceptionHandler::class, $handler);
        $this->at('2026-09-28 08:00:00 UTC');
        $organization = new Organization();
        $organization->forceFill(['id' => str_repeat('o', 16), 'timezone' => 'UTC']);
        $this->type = new AppointmentType();
        $this->type->forceFill([
            'id' => str_repeat('t', 16), 'organization_id' => $organization->getKey(),
            'is_active' => true, 'buffer_before_minutes' => 0, 'buffer_after_minutes' => 0,
        ])->setRelation('organization', $organization);
        $this->type->exists = true;
        $this->resource = new Resource();
        $this->resource->forceFill(['id' => str_repeat('r', 16), 'type' => 'person', 'is_active' => true]);
        $this->resource->exists = true;
        $this->start = CarbonImmutable::parse('2026-09-28 10:00:00 UTC');
        $this->end = $this->start->addHour();
        $this->cache = new ResourceAvailabilityCache();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        CarbonImmutable::setTestNow();
        Model::clearBootedModels();
        Model::unsetEventDispatcher();
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication(null);
        Container::setInstance(null);
        Mockery::close();
        parent::tearDown();
    }

    #[DataProvider('booleanValues')]
    public function test_both_boolean_values_are_cached_without_sliding_the_deadline(bool $value): void
    {
        $calls = 0;
        $loader = function () use (&$calls, $value): bool { $calls++; return $value; };
        $this->assertSame($value, $this->read($loader));
        $this->at('2026-09-28 08:00:20 UTC');
        $this->assertSame($value, $this->read($loader));
        $this->assertSame(1, $calls);
        $this->at('2026-09-28 08:00:31 UTC');
        $this->assertSame($value, $this->read($loader));
        $this->assertSame(2, $calls);
    }

    public static function booleanValues(): array { return [[true], [false]]; }

    public function test_fresh_and_transactional_calls_neither_read_nor_populate_results(): void
    {
        $this->assertTrue($this->read(fn () => true));
        $this->assertFalse($this->read(fn () => false, true));
        $this->assertTrue($this->read(fn () => false));
        $this->transactionLevel = 1;
        $this->assertFalse($this->read(fn () => false));
        $this->transactionLevel = 0;
        $this->assertTrue($this->read(fn () => false));
    }

    public function test_zero_ttl_and_unsaved_models_bypass_caching(): void
    {
        $this->container['config']->set('resource-availability-cache.seconds', 0);
        $this->assertTrue($this->read(fn () => true));
        $this->assertFalse($this->read(fn () => false));
        $this->container['config']->set('resource-availability-cache.seconds', 30);
        $this->resource->exists = false;
        $this->assertTrue($this->read(fn () => true));
        $this->assertFalse($this->read(fn () => false));
    }

    public function test_key_separates_type_organization_precision_buffers_and_quantity(): void
    {
        $originalType = clone $this->type;
        $originalResource = clone $this->resource;
        $originalStart = $this->start;
        $this->assertTrue($this->read(fn () => true));
        $changes = [
            fn () => $this->type->setAttribute('id', str_repeat('x', 16)),
            fn () => $this->type->setAttribute('organization_id', str_repeat('y', 16)),
            fn () => $this->type->setAttribute('buffer_before_minutes', 5),
            fn () => $this->type->setAttribute('buffer_after_minutes', 5),
            fn () => $this->resource->setRelation('pivot', new Pivot(['quantity_required' => 2])),
            fn () => $this->start = $this->start->addMicrosecond(),
        ];
        foreach ($changes as $change) {
            $this->type = clone $originalType;
            $this->resource = clone $originalResource;
            $this->start = $originalStart;
            $change();
            $this->assertFalse($this->read(fn () => false));
        }
    }

    public function test_shared_resource_invalidation_does_not_flush_unrelated_resources(): void
    {
        $firstType = clone $this->type;
        $this->assertTrue($this->read(fn () => true));
        $this->type->organization_id = str_repeat('b', 16);
        $this->assertTrue($this->read(fn () => true));
        $this->resource->id = str_repeat('z', 16);
        $this->assertTrue($this->read(fn () => true));
        $this->cache->invalidate([], [str_repeat('r', 16)]);
        $this->assertTrue($this->read(fn () => false));
        $this->resource->id = str_repeat('r', 16);
        $this->assertFalse($this->read(fn () => false));
        $this->type = $firstType;
        $this->assertFalse($this->read(fn () => false));
    }

    public function test_invalidation_is_deferred_until_commit(): void
    {
        $this->read(fn () => true);
        $this->transactionLevel = 1;
        $this->cache->invalidate([], [$this->resource->getKey()]);
        $this->assertCount(1, $this->commits);
        $this->transactionLevel = 0;
        $this->assertTrue($this->read(fn () => false));
        foreach ($this->commits as $commit) { $commit(); }
        $this->assertFalse($this->read(fn () => false));
    }

    public function test_calendar_hit_cannot_extend_calendar_freshness_by_another_thirty_seconds(): void
    {
        $calls = 0;
        $calendarLoader = function () use (&$calls): array { $calls++; return []; };
        $this->cache->calendar('connection', 'range', false, $calendarLoader);
        $this->at('2026-09-28 08:00:29 UTC');
        $this->assertTrue($this->read(function () use ($calendarLoader): bool {
            $this->cache->calendar('connection', 'range', false, $calendarLoader);
            return true;
        }));
        $this->assertSame(1, $calls);
        $this->at('2026-09-28 08:00:31 UTC');
        $this->assertFalse($this->read(fn () => false));
    }

    public function test_computation_time_counts_towards_ttl(): void
    {
        $this->assertTrue($this->read(function (): bool {
            $this->at('2026-09-28 08:00:31 UTC');
            return true;
        }));
        $this->assertFalse($this->read(fn () => false));
    }

    public function test_invalidation_during_computation_prevents_stale_publication(): void
    {
        $this->assertTrue($this->read(function (): bool {
            $this->cache->invalidate([], [$this->resource->getKey()]);
            return true;
        }));
        $this->assertFalse($this->read(fn () => false));
    }

    public function test_generation_eviction_does_not_resurrect_old_entries(): void
    {
        $this->read(fn () => true);
        $this->store->forget('resource_availability:generation:resource:'.bin2hex($this->resource->getKey()));
        $this->assertFalse($this->read(fn () => false));
    }

    public function test_cache_failure_executes_the_real_loader_once(): void
    {
        $this->cacheOutage = true;
        $calls = 0;
        $this->assertFalse($this->read(function () use (&$calls): bool { $calls++; return false; }));
        $this->assertSame(1, $calls);
    }

    public function test_loader_exceptions_are_not_swallowed_or_cached(): void
    {
        $calls = 0;
        for ($i = 0; $i < 2; $i++) {
            try {
                $this->read(function () use (&$calls): bool { $calls++; throw new \LogicException('Calculation failed'); });
                $this->fail('The loader exception must propagate.');
            } catch (\LogicException $exception) {
                $this->assertSame('Calculation failed', $exception->getMessage());
            }
        }
        $this->assertSame(2, $calls);
    }

    private function read(callable $loader, bool $fresh = false): bool
    {
        return $this->cache->resource($this->resource, $this->type, $this->start, $this->end, $fresh, $loader);
    }

    private function at(string $time): void
    {
        $now = CarbonImmutable::parse($time);
        Carbon::setTestNow($now);
        CarbonImmutable::setTestNow($now);
    }
}
