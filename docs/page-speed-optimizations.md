# Page loading performance patch

Base: `OKayInc/bookings` commit `e27416b39ac231b8b0bd665ccb965bb82489dd3c`.
Apply this after the earlier availability/queue optimization patch, which is already part of this base.

## Changes

- Public appointment lists batch-load the questionnaire and conditional-resource definitions needed for pricing, plus the current organization's inherited resource defaults. These definitions use the existing configuration cache, including its Redis configuration and invalidation. The catalogue key is versioned so an old cache entry cannot omit the newly loaded relationships.
- GET/HEAD page responses reuse active memberships for organization selection, permission checks and navigation. Plan grant/subscription records are also reused within that response. Grant start/end dates and trial/grace expiry are evaluated on every entitlement check.
- Request data is discarded when the response finishes. POST requests, console jobs and reads inside transactions bypass this cache. SQL writes, including bulk query-builder updates, and transaction boundaries clear it. No membership or entitlement decisions are persisted in Redis.
- The dashboard checklist batch-loads inherited defaults, calculates each appointment type's payment requirement once, and uses a resource-ID lookup instead of repeatedly searching the resource collection. Dashboard counters are fetched together.

The checklist still renders in the initial HTML and reads current configuration on every visit. Its checks, links, permissions and time-sensitive event/opening checks retain their existing behavior. Reservation transactions continue to read resource defaults from SQL.

## Deploy

No database migration, new environment setting, dependency update or cron change is required. Keep the existing scheduler and queue workers.

Use the normal release process and run commands as the website's Unix user. This example assumes the downloaded patch is at `/tmp/appointment-to-page-speed-optimizations.patch` and Composer is on that user's PATH:

```bash
cd /var/www/appointment.to/web
git apply --check /tmp/appointment-to-page-speed-optimizations.patch
git apply /tmp/appointment-to-page-speed-optimizations.patch
/usr/bin/php84 "$(command -v composer)" dump-autoload --optimize
/usr/bin/php84 artisan optimize
/usr/bin/php84 artisan queue:restart
```

If web PHP uses OPcache with timestamp validation disabled, reload it through the normal deployment procedure. The configuration cache continues to use the existing `CONFIGURATION_CACHE_STORE` and `CONFIGURATION_CACHE_TTL` settings.

## Validation and measurement

Validated with PHP 8.4.27 and MariaDB 10.11.14: **697 tests passed, 4,156 assertions; one optional real-Redis integration test skipped**. The ten added regressions passed. All 15 changed/new PHP files passed syntax checks.

The added regressions exercise real HTTP middleware outside test transactions and verify:

- One membership lookup serves dashboard organization selection, permission checks and navigation.
- Catalogue and checklist SQL counts stay bounded when growing a fixture from one to eight appointment types, including inherited and conditional resources.
- A warm catalogue computes its pricing summary without extra SQL, and resource edits invalidate the cached price inputs.
- Organization/user isolation, membership suspension, bulk role changes and rollback cannot reuse stale page permissions.
- Scheduled grants and trial expiry are evaluated correctly within one response; plan mutations and missing subscriptions are handled correctly.
- Shared-resource defaults stay specific to each organization; transactions read fresh defaults.
- Checklist results reflect resource/pivot edits and expired opening exceptions on the next call.

Run against the project's dedicated MariaDB test database:

```bash
/usr/bin/php84 artisan test --filter='PageReadOptimizationTest|CatalogueChecklistPerformanceTest'
/usr/bin/php84 artisan test
```

These are query-count and behavior regressions, not production latency measurements. Compare server response time and query count for the public appointment list and dashboard before and after deployment, using the same organization and both cold and warm caches. No production speedup percentage is claimed.

## Rollback

Reverse the patch in the release checkout, regenerate Composer autoload files and Laravel caches, and restart workers through the normal deployment process. No data migration needs reversing. The versioned catalogue entries expire under the existing configuration-cache TTL.
