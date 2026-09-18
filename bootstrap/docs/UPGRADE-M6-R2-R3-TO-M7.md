# Upgrade M6-R2-R3 -> M7

1. Back up the application database and `.env`.
2. Apply the M7 source/patch while preserving `.env` and runtime `storage` files.
3. Configure Google and/or Microsoft OAuth credentials as documented in `docs/CALENDAR-INTEGRATIONS.md`.
4. Run:

```bash
php artisan optimize:clear
php artisan migrate
php artisan test
```

Do **not** run `migrate:fresh`.

M7 adds only these migrations:

- `2026_08_25_000042_create_calendar_connections_table.php`
- `2026_08_25_000043_create_external_calendars_table.php`
- `2026_08_25_000044_create_appointment_type_calendars_table.php`
- `2026_08_25_000045_create_appointment_external_events_table.php`

No Composer dependency is added in M7; provider HTTP calls use Laravel's HTTP client.

Keep Laravel Scheduler running. M7 adds `appointments:sync-calendars` every five minutes.
