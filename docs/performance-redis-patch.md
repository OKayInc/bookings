# Availability and background integration performance patch

Base: `OKayInc/bookings` commit `7f01b1be921b0303638b4a303324b34e8b0668bd`.

## Changes

- Replacement-resource browsing loads schedules, local conflicts and external calendar busy intervals once per requested range. The snapshot exists only within that call. Transactions retain the original fresh checks, and reservation locking is unchanged. `AVAILABILITY_BATCH_RESOURCE_CHECKS=false` disables the optimization.
- All application email notifications use the `notifications` queue after transaction commit. Notification payloads are encrypted with `APP_KEY`; this also permits the application's binary UUIDs to survive JSON queue transport.
- Booking creation, rescheduling, resource reassignment and cancellation enqueue meeting/calendar work. The worker prepares the meeting before writing calendar events. A SQL outbox (`appointment_sync_requests`) retains pending work through broker/provider failures. Shared Redis locks coalesce duplicate dispatches and prevent concurrent processing of one appointment. A generation check preserves edits committed during provider requests.
- The five-minute calendar task dispatches pending work. Hourly reconciliation checks the configured appointment window; matching payload hashes skip remote writes. A daily forced reconciliation at 03:15 in the application's scheduler timezone refreshes unchanged events too. New hashes are populated on the first successful sync after deployment.
- The resource list uses existence queries instead of counting allocations and displays 25 resources per page. Search/filter parameters survive pagination.

Meeting links and calendar changes may appear after the booking response, once workers finish. Booking emails contain the private management link; its page reflects current meeting status. The explicit staff action to retry conference creation retains its immediate diagnostic response.

## Apply and deploy

Apply from the repository root, using the patch's actual location:

```bash
git apply --check /path/to/appointment-to-speed-optimizations.patch
git apply /path/to/appointment-to-speed-optimizations.patch
```

Use the existing deployment process to keep web traffic, the scheduler and old workers paused while applying the migration and switching releases. For a single-server maintenance deployment:

1. Put the application in maintenance mode and stop existing queue workers/scheduler during the release change.
2. Apply the patch and run `php artisan migrate --force`.
3. Configure the environment below, then run `php artisan config:cache`.
4. Start the queue workers and resume the scheduler. Seed existing appointments with `php artisan appointments:sync-calendars --reconcile`.
5. Run `php artisan up` and verify one booking, its notification and its calendar event.

```dotenv
QUEUE_CONNECTION=redis
REDIS_CLIENT=phpredis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
REDIS_QUEUE_CONNECTION=default
REDIS_QUEUE_RETRY_AFTER=180
INTEGRATION_QUEUE=integrations
INTEGRATION_LOCK_STORE=redis
AVAILABILITY_BATCH_RESOURCE_CHECKS=true
```

Retain the installation's Redis credentials, database numbers and unique application prefixes. Redis and the PHP Redis extension must be available to both web PHP and CLI PHP. All application nodes must share the same integration lock store. Keep `APP_KEY` consistent across web and workers; encrypted jobs require it. Existing Memcached/configuration/availability cache settings can remain in place.

Queue data needs persistence and an eviction policy that does not discard jobs. Use a dedicated Redis instance for queues where possible; Redis logical databases do not isolate memory eviction policies. Only integration requests have a SQL outbox in this patch. Email delivery depends on durable Redis queue storage and successful enqueueing.

## Workers and scheduler

Run integrations separately from email so a slow calendar provider does not delay verification emails. For example:

```bash
php artisan queue:work redis --queue=notifications,default --sleep=1 --tries=5 --backoff=30 --timeout=120 --max-time=3600
php artisan queue:work redis --queue=integrations --sleep=1 --tries=5 --timeout=120 --max-time=3600
```

Supervise these processes so they restart automatically. The integration job specifies retry backoff of 15, 60, 180 and 300 seconds. Keep worker/job timeout (120 seconds) below the processing lock lifetime (150 seconds), and Redis `retry_after` (180 seconds) above both. PHP CLI needs PCNTL for worker timeouts.

Example systemd template, with paths and service account adjusted for the installation:

```ini
# /etc/systemd/system/appointment-queue@.service
[Unit]
Description=Appointment queue %i
After=network.target

[Service]
User=nginx
Group=nginx
WorkingDirectory=/var/www/appointment
ExecStart=/usr/bin/php artisan queue:work redis --queue=%i --sleep=1 --tries=5 --backoff=30 --timeout=120 --max-time=3600
Restart=always
RestartSec=3
KillSignal=SIGTERM
TimeoutStopSec=180

[Install]
WantedBy=multi-user.target
```

Enable instances for `notifications`, `integrations` and `default`. Preserve the existing once-per-minute `php artisan schedule:run` cron entry (or supervised `schedule:work`); do not add a duplicate scheduler. Restart workers on subsequent deployments so they load the new code/configuration. `php artisan queue:restart` requires the workers and deployment process to share the configured cache store.

## Recovery and monitoring

- Track queue age, `php artisan queue:failed`, and the oldest `requested_at_utc`/`last_error` in `appointment_sync_requests`. Pending requests are retried by the five-minute scheduler; a dispatch uniqueness lock can delay redispatch for up to 15 minutes after an interrupted enqueue.
- `php artisan appointments:sync-calendars` dispatches due pending requests. `--reconcile` also discovers appointments in the configured window and skips identical payloads; `--force` writes them even when unchanged. The configured window remains two days back and 730 days ahead by default.
- Fix invalid provider credentials before retrying failed jobs. The generation check and database mappings make normal retries safe; no distributed transaction spans the database and remote providers. A remote create that succeeds immediately before a lost response/process crash can still need duplicate cleanup, and SMTP delivery is also at least once. This patch does not claim exactly-once delivery or add provider-specific idempotency APIs.
- Model observers mark relevant edits. Direct SQL/bulk changes that bypass observers are caught by hourly reconciliation. Reconciliation does not add new handling for provider-deleted events; inspect provider errors as before.
- For a code rollback, drain the new queues with the patched code before reverting, or retain the patched worker release until its jobs finish. Keep the additive tables/column during a code rollback. The migration's `down()` retains `failed_jobs` because an installation may already own that table.

## Validation

Validated with PHP 8.4.27, MariaDB 10.11.14 and Redis 7.0.15: **688 tests, 4,130 assertions, all passing**. This includes 12 added tests and an updated calendar test that now expects unchanged payloads to skip writes.

The replacement-resource regression checks identical slots with buffers, holds, calendar conflicts, inactive custom schedules, exceptions and quantity-managed equipment. In its cold-cache fixture, the batched path uses less than half the SQL queries and two external calendar reads for the whole range. It also checks that new conflicts appear on the next read and transactions bypass browsing snapshots. These are workload-specific operation counts, not a production latency benchmark.

Queue tests cover provider retries, failed cancellation deletion, meeting-before-calendar ordering, concurrent edits, rollback, broker failure, binary UUID/private-link serialization, and real Redis enqueue/pop/dispatch deduplication. Run the full suite against a dedicated test database and Redis instance:

```bash
RUN_REDIS_TESTS=1 php artisan test
```

`phpunit.xml` already enforces the test database connection and uses the array lock store for ordinary tests. The explicit Redis test switches its own queue/lock configuration; without `RUN_REDIS_TESTS=1` that one test is skipped. Configure `TEST_DB_*`, `REDIS_*` and a test-only Redis prefix for the local environment.
