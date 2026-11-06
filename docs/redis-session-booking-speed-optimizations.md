# Redis sessions and booking read optimizations

This patch makes Redis the default HTTP session backend and reduces repeated SQL and duplicate booking-flow work.

## Production environment

Use a dedicated Redis logical database for sessions so cache flushes do not log users out:

```dotenv
SESSION_DRIVER=redis
SESSION_CONNECTION=session
REDIS_SESSION_DB=2
```

Existing Redis queue/general data remains on `REDIS_DB` and the application/configuration caches remain on `REDIS_CACHE_DB`.

## Main performance changes

- HTTP sessions default to Redis instead of filesystem storage.
- Public booking step two embeds the quote already calculated while rendering, avoiding the immediate duplicate quote HTTP request.
- Conditional unavailable-resource defaults are calculated once per step-two page render.
- Group availability calculates booked and held attendee totals with SQL aggregates instead of hydrating every booking and hold.
- Ticketed group availability batches event-start validation instead of issuing one event-occurrence query per existing appointment.
- Coupon appointment-type validation uses one `WHERE IN` query instead of one query per selected type.
- Public organization gallery, appointment gallery/contract definitions, organization holidays, and public coupon discovery participate in the Redis configuration cache with write invalidation.
- Resource holiday checks reuse loaded organization/resource pivot configuration during non-transactional browsing.
- The public catalogue prefetches the selected appointment detail; the booking page warms short-lived availability caches only after the scheduler approaches the viewport.
- A composite appointment index supports group availability filters by type, status, duration and start time.

Reservation creation, locking and final booking writes continue to use authoritative SQL checks.

## Deployment

After deploying the code and updating the production `.env` values above:

```bash
cd /var/www/appointment.to/web &&
/usr/bin/php84 artisan down &&
/usr/bin/php84 artisan migrate --force &&
/usr/bin/php84 artisan optimize &&
/usr/bin/php84 artisan queue:restart &&
/usr/bin/php84 artisan up
```

If PHP OPcache timestamp validation is disabled, perform the normal PHP-FPM/Apache reload before `artisan up`.

To measure the effect, temporarily enable:

```dotenv
PERFORMANCE_LOG_ENABLED=true
PERFORMANCE_LOG_SAMPLE_RATE=1
```

Then rebuild configuration with `artisan config:cache` and compare `duration_ms`, `db_ms`, and `query_count` for the public catalogue, appointment detail, slot and booking-hold routes.
