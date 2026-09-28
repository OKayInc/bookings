# Resource availability browsing cache

## Configuration and deployment

```dotenv
RESOURCE_AVAILABILITY_CACHE_STORE=redis
RESOURCE_AVAILABILITY_CACHE_SECONDS=30
```

These are the defaults. Set `RESOURCE_AVAILABILITY_CACHE_SECONDS=0` to disable
resource-result caching. Use the same Redis endpoint, database and cache prefix
on every HTTP/worker node. A node-local cache cannot coordinate invalidation
across the cluster. No migration, Composer dependency, asset build, or new cron
entry is required.

After deploying the code, rebuild configuration on every node:

```sh
php artisan config:cache
```

Reload the PHP process serving the application if your OPcache deployment policy
requires it. Do not flush the entire Redis database: the new key namespace avoids
collisions with existing entries, and old snapshots expire automatically.

## Behaviour and safety

`AppServiceProvider` binds `AvailabilityService` to `CachedAvailabilityService`.
The latter overrides only `isResourceAvailableAt()` and delegates misses and
all authoritative checks to the original implementation. Slot generation and
existing database/resource locks are unchanged. Resolve the service through
Laravel's container rather than manually constructing it to use this decorator.

Both `true` and `false` can be cached for **at most** the configured lifetime.
Hits do not extend that lifetime, and calculation time consumes it. Keys include
the organization, appointment type, resource, exact UTC start/end (including
microseconds), buffers, required quantity, and relevant supplied model state.

The result cache is neither read nor populated when the existing
`$freshExternalCalendars` flag is true, while a database transaction is active,
when disabled, or for unsaved resources/types. A cached browsing result never
authorizes a hold or booking. The final external-calendar checks retain their
existing fresh flag. Existing calendar-only caching behaviour remains available
for other non-fresh callers.

Cache access failures are reported once per cache-service instance and fall back
to the original calculation. They never imply that a resource is available.
Calculation exceptions propagate; the loader is not silently run a second time.
Required-calendar provider errors retain the existing fail-closed behaviour and
are not published as resource snapshots.

A slot may disappear between browsing and selecting it, and an expired hold can
remain displayed as busy until the result expires or the expiry task invalidates
it. These are expected bounded browsing delays, not reservation guarantees.

## Calendar freshness

Calendar cache entries now store the actual snapshot expiry along with their
intervals. Every calendar read, including a hit, bounds the enclosing resource
result's expiry. For example, resource computation using calendar data with one
second remaining can cache its result for no longer than that second. Multiple
calendars use the earliest expiry. This prevents two consecutive 30-second caches
from retaining calendar information for almost 60 seconds.

Calendar intervals continue to use the application's default cache store.
Generation metadata uses `RESOURCE_AVAILABILITY_CACHE_STORE` so every node sees
invalidation. Legacy `calendar_busy:` entries are not reused because they have no
freshness metadata. Calendar range keys now preserve UTC microsecond precision.
`CalendarManager::forgetBusyCache()` performs actual generation invalidation.

## Invalidation

`ResourceAvailabilityObserver` handles organization, appointment-type, resource,
appointment, booking, hold, schedule, weekly-rule, exception, calendar-connection,
external-calendar and external-event model changes. It captures dependency IDs
before model deletion/cascades. Invalidation is scheduled on the model's database
connection after the outer transaction commits; rollback discards the callbacks.

Organization generations invalidate that organization's results. Resource
generations are global to the resource ID, not to its owning organization, so a
hold on a shared resource invalidates other organizations using it. Unrelated
organizations/resources are not globally flushed. In-flight calculations check
generations again before publication and cannot republish into the new generation.
Missing/evicted generation keys are initialized atomically with new random tokens;
a missing key cannot resurrect an old initial-generation entry.

`AllocationMutationBuilder` on appointments and holds captures matching row and
resource IDs under a transaction before bulk Eloquent updates/deletes. It covers
the existing release/expiry services, cancellation and API bulk-release paths
without changing those call sites or replacing a bulk update with per-row writes.
This adds bounded metadata queries to writes in exchange for eliminating repeated
queries on cache hits.

`AvailabilityAllocationPivot` observes normal `attach`, `sync`, explicit-ID
`detach`, and `updateExistingPivot` calls through appointment/hold `resources()`
relations. Existing explicit `ConfigurationCache::invalidate*()` calls also
invalidate resource results, including configuration pivot edits. Saving
appointment-type calendar preferences explicitly invalidates the affected scopes.

Raw SQL, `DB::table(...)` writes, imports, query-level edits to configuration
models, operations that deliberately suppress model events, and relation bulk
operations that bypass custom pivot events must invalidate explicitly. For
example, after changing known resource allocations:

```php
app(\App\Domain\Availability\ResourceAvailabilityCache::class)->invalidate(
    organizationIds: [$organization->getKey()],
    resourceIds: [$resource->getKey()],
);
```

IDs are binary UUID model keys, not the printable UUID strings. Calling this
inside the same transaction defers invalidation until commit. Scripts touching
calendar state can supply `connectionIds` as well. Do not use a global cache flush.

## Tests

The unit contract tests use an array cache and mocked connection boundaries; they
do not require MariaDB or Redis:

```sh
vendor/bin/phpunit tests/Unit/ResourceAvailabilityCacheTest.php
```

The integration class uses `DatabaseMigrations`, not `RefreshDatabase`, because a
per-test outer transaction would deliberately bypass the result cache. Run it
**only against the project's dedicated MariaDB testing connection/database**:

```sh
php artisan test tests/Feature/ResourceAvailabilityCacheIntegrationTest.php
php artisan test --filter='AvailabilityEngineTest|SharedResourceTest|ResourceRequirementTest|CalendarDefaultsTest|RegionalHolidayAvailabilityTest|M9R1EquipmentSupportTest'
```

Coverage includes repeated reads with no SQL, true/false TTLs, exact key separation,
fresh/transaction bypass, shared-resource invalidation, pivot changes, bulk hold
release/expiry, pre-cascade deletion, schedule edits, rollback, in-flight writes,
calendar TTL propagation, Redis failure and a deliberately incorrect cached true
that cannot authorize a conflicting hold.

### Validation performed while preparing this change

PHP syntax checks and 22 isolated contract checks (50 assertions) passed using
PHP 8.4.23. Those isolated checks executed the real cache class with small framework
stand-ins; they are **not** Eloquent, MariaDB or Redis integration tests.

The PHPUnit suites and live Redis/MariaDB concurrency tests could not be run in
the preparation environment: Composer dependencies, MariaDB/PDO MySQL, and Redis
were unavailable. Run the commands above on the dedicated test environment before
merging/deploying. No production timing improvement or full-suite pass is claimed.
