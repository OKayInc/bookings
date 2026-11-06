# Booking, coupon and event page performance

Patch base: `ccd1351555b1426e611dae6057ba90fd768c0a89` (`OKayInc/bookings`, main). Apply this after the gallery-thumbnail/customer-history patch.

## Changes

- Booking details reuse their loaded payment/refund ledger to calculate both displayed refund limits. Existing payment ordering, deposit allocation and pending/succeeded refund handling are preserved. Actual refund commands still query current records and retain their existing transaction/locking checks. The page no longer loads unused refund-to-transaction relations. Schedule proposals expire before their history is loaded, so the displayed history is fetched once and reflects newly expired proposals.
- The coupon management page shows 25 issued codes and 25 offers per page, with independent Bootstrap pagination and links back to the relevant section. Equal creation timestamps use descending IDs for stable ordering. Tenant filtering, balances, print links, destruction restrictions and pending/failed refund retries are preserved. The list uses an existence check for redemptions and loads only retryable refunds, rather than loading all redemption/payment history. Active appointment types remain available in the creation forms.
- `currentEventOccurrence()` reuses its result, including an empty result, during a GET/HEAD response. The existing request read cache is discarded after the response, invalidated by SQL writes and transaction boundaries, and bypassed inside transactions and on POST requests. Future-event selection and the most recent active past-event fallback are unchanged. No event selection is saved in Redis.
- Optional sampled HTTP timing logs record route name, method, response status, application duration, cumulative SQL time and query count. Counters live on the current request, do not enable SQL query logging, and are discarded after success or failure. Timing-log write failures do not interrupt the response.

There are **no new migrations, dependencies, background jobs or backfill commands** in this patch. Existing crontab and Redis settings stay the same.

## Complete deployment commands

Use the website's Unix account. Copy the patch to `/tmp/appointment-to-detail-page-speed.patch`, then run:

```bash
cd /var/www/appointment.to/web &&
git apply --check /tmp/appointment-to-detail-page-speed.patch &&
/usr/bin/php84 artisan down &&
git apply /tmp/appointment-to-detail-page-speed.patch &&
/usr/bin/php84 "$(command -v composer)" dump-autoload --optimize &&
/usr/bin/php84 artisan migrate --force &&
/usr/bin/php84 artisan optimize &&
/usr/bin/php84 artisan queue:restart
```

The chain stops on an error. Resolve any error before reopening the site. `migrate --force` checks for pending migrations from earlier releases; this patch itself adds none. If OPcache timestamp validation is disabled, perform your usual PHP-FPM/Apache deployment reload now. Then reopen the site:

```bash
cd /var/www/appointment.to/web &&
/usr/bin/php84 artisan up
```

The speed improvements work with timing logs disabled. No environment changes are required for them.

## Optional timing measurements

To collect a sample of about 10% of HTTP requests, set these values in the production `.env` file, replacing existing entries if present:

```dotenv
PERFORMANCE_LOG_ENABLED=true
PERFORMANCE_LOG_SAMPLE_RATE=0.1
```

Refresh cached configuration:

```bash
cd /var/www/appointment.to/web &&
/usr/bin/php84 artisan config:cache
```

Sampling is uniform across requests, not restricted to slow responses. Set the rate to `1` temporarily when comparing a small set of pages so every request is recorded; set it back to `0.1` for ongoing observation. `0` disables collection. To turn logging off, set `PERFORMANCE_LOG_ENABLED=false` and rebuild the configuration cache. Reload any persistent application server after configuration changes through your normal deployment procedure.

Read the logs with:

```bash
cd /var/www/appointment.to/web &&
tail -n 50 storage/logs/performance-*.log
```

Daily log files use the `performance-YYYY-MM-DD.log` name and retain seven files. A file appears only after the first sampled request. The log context contains only `route`, `method`, `status`, `duration_ms`, `db_ms` and `query_count`. It contains no URLs, query parameters, SQL text/bindings, credentials, customer identifiers or form data. Unnamed routes are grouped as `unnamed`. No timing headers or diagnostics are added to public responses.

`duration_ms` measures the HTTP middleware pipeline, including route execution and normal view rendering, before writing the timing log. It excludes PHP/application bootstrap, web-server/proxy waiting, response transmission, streamed response bodies, browser assets and deferred/queued work. `db_ms` is the sum of Laravel's recorded query execution durations during that pipeline. Compare repeated visits to the same route and organization with both cold and warm caches. These measurements identify remaining application/database work; they are not browser page-load measurements.

## Validation

Regression coverage includes:

- One payment-ledger query on the booking page, unchanged deposit/price refund limits, preserved payment ordering, fresh action reads despite loaded stale relations, and proposal expiry before displaying history.
- A 53-coupon history paged as 25/25/3 and a separate 27-offer history, stable tie ordering, organization isolation, authorization, empty lists, destruction restrictions and refund retries.
- One current-event query for a future event, two for the existing past/empty fallback, next-request freshness, separation between types, SQL-write/transaction invalidation and POST bypass.
- Disabled and sampled timing logs, counters isolated between successive requests, exclusion of request/SQL contents, handled error responses, logging failures and exception cleanup.

Verified with PHP 8.4.27, MariaDB 10.11.14 and the repository's locked dependencies: **723 tests passed, one optional real-Redis test skipped, 4,403 assertions**. The focused regression group passed 49 tests. PHP syntax checks passed for all 15 changed/new PHP files, and Laravel configuration, event, route and view cache builds passed. PHPUnit used the repository's 512 MB memory limit. No production response-time percentage is claimed.

## Rollback

Restore the previous code through the normal release process, regenerate the optimized Composer autoloader and Laravel caches, and restart workers. No database rollback is needed. Existing optional timing logs can remain on disk.
