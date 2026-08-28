# Upgrade M2-R5 to M3

M3 is an additive upgrade. Do **not** use `migrate:fresh` on an existing installation.

## 1. Back up

Back up the MariaDB database and application files before upgrading.

## 2. Replace/apply source

Either deploy the full M3 source tree or apply the supplied M2-R5 → M3 patch.

Preserve your real `.env` and production `APP_KEY`.

## 3. Clear cached Laravel state

```bash
php artisan optimize:clear
```

## 4. Run additive migrations

```bash
php artisan migrate
```

M3 adds migrations `000013` through `000018` and does not delete M1/M2 data.

## 5. Configure availability

Open the backend **Availability** page and configure organization working hours.

M3 intentionally does not invent default business hours for existing organizations. Until an effective schedule is configured, generated appointment availability is closed.

Resource and appointment-type schedules are optional overrides and inherit organization hours when absent.

## 6. Configure Laravel Scheduler

Temporary holds must be expired regularly. Ensure the server invokes Laravel Scheduler every minute:

```cron
* * * * * cd /path/to/appointment-software && php artisan schedule:run >> /dev/null 2>&1
```

You can test hold cleanup manually:

```bash
php artisan appointments:expire-holds
```

## 7. Run tests

Using your existing dedicated MariaDB test database:

```bash
php artisan test
```

See `docs/TESTING.md` for the safety guard and `TEST_DB_*` settings.

## 8. Preview slots

Use **Availability → Preview slots** to confirm that organization/resource/type intersection and exceptions produce the intended start times before M4 is installed.
