# Configuration cache

Public appointment pages and booking read endpoints reuse organization configuration,
appointment types, questionnaires, resource definitions, taxes, locations, cancellation
and rescheduling policies, and branding from a shared Redis cache. Availability,
inventory, holds, payments, and booking writes always read current database state.

Set `CONFIGURATION_CACHE_STORE=redis` on all HTTP nodes. Configure `REDIS_HOST`,
`REDIS_PORT`, `REDIS_PASSWORD`, and `REDIS_CACHE_DB` for the same Redis server on every
node. The default cache lifetime is 600 seconds; change `CONFIGURATION_CACHE_TTL`
if needed, or set it to 0 to bypass configuration reads while debugging. Redis
requires the PHP Redis extension (or another configured Laravel Redis client).
Keep the Redis cache database separate from sessions and queues if those use Redis.
After changing `.env`, run `php artisan config:clear` on each node, or rebuild its
configuration cache with `php artisan config:cache`.

Model observers rotate an organization-specific cache generation when any cached
definition is created, changed, or deleted. Invalidation happens immediately for
same-transaction reads and again after commit, so rolled-back writes do not expose
uncommitted configuration across nodes. Resource and questionnaire pivot changes
also invalidate explicitly because Eloquent does not dispatch model events for
`sync()` or `updateExistingPivot()`. Old generations expire after the configured
cache lifetime. SQL remains authoritative, including booking locks and availability.

If you change configuration through direct SQL, imports, or a script that suppresses
Eloquent model events, call `ConfigurationCache::invalidate($organizationId)` after
committing. To clear all configuration entries, run `php artisan cache:clear redis`
against the shared Redis cache store; this also removes other entries in that store.
