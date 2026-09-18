# Upgrade current repository to M7-R2

This upgrade is designed for the current `OKayInc/bookings` repository state at commit:

`0f90c7a373ea8270d5c6f95f0317f3e4b4fbd777`

The repository's manual `.htaccess` and email-verification changes are retained.

## Upgrade

Back up the application/database and preserve `.env` plus runtime `storage`, then apply the current-repository → M7-R2 patch.

Optionally configure the OAuth transaction lifetime:

```dotenv
CALENDAR_OAUTH_STATE_TTL_MINUTES=15
```

The Google and Microsoft callback URLs have not changed. Do not alter existing provider redirect URI registrations solely for M7-R2.

Then run:

```bash
php artisan optimize:clear
php artisan migrate
php artisan test --filter=BackendEmailVerificationTest
php artisan test --filter=CalendarOauthStateTest
php artisan test
```

Do not run `migrate:fresh` on an existing installation.
