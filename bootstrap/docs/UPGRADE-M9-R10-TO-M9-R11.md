# Upgrade M9-R10 to M9-R11

1. Back up MariaDB, `.env`, and the persistent `storage` directory.
2. Deploy the M9-R11 source while preserving environment-specific files and installed dependencies.
3. Run:

```bash
php artisan optimize:clear
php artisan migrate --force
php artisan test
php artisan optimize
```

4. Confirm the Laravel scheduler still runs every minute. M9-R11 adds `appointments:disclose-event-locations`, scheduled every ten minutes.
5. Edit a free ticketed appointment type, enable private admission, and confirm at least one active owner, administrator, or manager has a primary email address.
6. Submit a test request and verify that coordinator email, first-decision enforcement, attendee ticket privacy, acceptance/decline, and location disclosure behave as configured.

The migration is additive. Existing ticketed events remain public, do not require admission approval, and use immediate location disclosure unless changed by an authorized user.
