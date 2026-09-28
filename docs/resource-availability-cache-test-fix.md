# Resource availability cache: isolated-test fix

## Symptom and cause

The reported `ResourceAvailabilityCacheTest` failures share the exception
`Target class [config] does not exist`, reached from the call to
`$type->loadMissing('organization')` in `ResourceAvailabilityCache::resource()`.

Laravel 13's Eloquent collection `loadMissing()` constructs a query builder
before checking whether each requested relationship is already loaded. The unit
fixture already supplies the organization, but it replaced only the container
and facade services, not Eloquent's separate static connection resolver. A
resolver left over from a previous Laravel application can therefore consult a
torn-down application rather than the fixture's configured container. In a
standalone run, an absent Eloquent resolver can fail on the same path.

This is not evidence that the production `.env` lacks cache settings. Clearing
configuration, disabling the cache, or bypassing the failing assertions is not
the correction.

## Correction

`ResourceAvailabilityCache::resource()` checks `relationLoaded('organization')`
before calling `loadMissing()`. An already-loaded graph needs no Eloquent query
builder; an unloaded organization is still retrieved normally.

The unit fixture saves and clears Eloquent's connection resolver, then restores
it and the previous event dispatcher/container/facade application in teardown.
Cleanup runs even if Mockery verification throws. DB facade transaction checks
remain mocked, and no real SQL or Redis service is introduced into the unit
suite.

The 30-second lifetime, keys, invalidation, calendar-expiry propagation and
fresh/transaction bypasses are unchanged. All existing test assertions remain.
New tests cover preloaded organizations with no resolver, preloaded organizations
with an inherited resolver that must never be used, and a genuinely unloaded
organization in the MariaDB integration suite.

## Verification

With the project's Composer dependencies installed, run the isolated tests:

```sh
vendor/bin/phpunit tests/Unit/ResourceAvailabilityCacheTest.php
```

Only against the dedicated MariaDB test database, run the integration tests:

```sh
php artisan test tests/Feature/ResourceAvailabilityCacheIntegrationTest.php
```

Also run the full suite to check test-order isolation. The integration class uses
`DatabaseMigrations`; do not run it against production.

No migration, dependency update, environment-variable change, cache flush or
additional cron entry is required by this fix. Follow the normal code deployment
and PHP/OPcache reload procedure.
