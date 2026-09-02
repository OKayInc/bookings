# Upgrade M8 → M8-R1

M8-R1 is an additive ticketing revision. Do not run `migrate:fresh` on an existing installation.

1. Back up MariaDB, `.env`, `APP_KEY`, and runtime `storage`.
2. Deploy the complete M8-R1 source while preserving environment and runtime files.
3. Install production dependencies, migrate, and rebuild caches:

```bash
git pull --ff-only
composer install --no-dev --optimize-autoloader
php artisan optimize:clear
php artisan migrate --force
php artisan optimize
```

No new Composer package, API credential, environment variable, queue worker or scheduled command is required.

## Verification on a dedicated test host

Configure a separate MariaDB test database whose name contains `test`; never point the test suite at production.

```bash
php artisan test --filter=TicketingTest
php artisan test --filter=PerAttendeePricingTest
php artisan test --filter=QuestionnaireBookingFlowTest
php artisan test --filter=BladeCompilationTest
php artisan test
node --test tests/JavaScript/*.test.cjs
./vendor/bin/pint --test
```

Manual checks:

1. Start with Single attendance, Variable duration and Fixed total or Duration rate pricing. Enable ticketing and confirm the form changes to Group, Fixed and Per attendee, with the incompatible options unavailable.
2. Uncheck ticketing and confirm Single, Variable, Fixed total and Duration rate become available again.
3. Configure two paid seating blocks with different per-ticket fees. Confirm slot and checkout totals reflect the automatically allocated block and the fee is itemized.
4. Configure a free event and confirm seating-fee fields are hidden. A direct request containing a fee must be rejected.
5. Hold the last numbered seat in one browser and verify another buyer cannot reserve it. Complete the first booking and confirm its ticket and booking price line retain the held fee.

## Rollback

The previous M8 code does not understand held ticket seats or per-ticket seating fees. Stop new bookings and restore the pre-upgrade database/application backup for a complete rollback. If this migration is still the most recent migration and no M8-R1 bookings must be preserved, `php artisan migrate:rollback --step=1 --force` removes only the two additive columns; verify the migration plan before running it.
