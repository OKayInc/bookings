# Upgrade M7-R2 to M7-R2-R1

Apply the source patch or replace the application source while preserving `.env` and runtime `storage`.

No migration or Composer update is required.

```bash
php artisan optimize:clear
php artisan test --filter=CalendarProviderTest
php artisan test
```

After deployment, open **Calendar connections** and use **Refresh calendars** on the already-connected Google account. Re-authorizing Google is not required.
