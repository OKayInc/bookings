# Upgrade M7-R18 to M7-R19

1. Back up MariaDB, `.env`, `APP_KEY`, and runtime `storage`.
2. Apply `m7-r18-to-m7-r19.patch` to the M7-R18 source tree, or deploy the full M7-R19 source ZIP while preserving configuration and runtime files.
3. Install existing locked production dependencies, migrate, and clear caches:

```bash
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan optimize:clear
```

Do not run `migrate:fresh` on an existing installation. No new Composer dependencies are required.

4. Edit a group appointment type, select **Per attendee**, and choose the calculation mode. For ranges, cover every count from 1 through the session capacity without gaps or overlaps. Increasing capacity may require extending the final range.
5. Verify the public preview and checkout totals. Group sessions continue to allow separate clients to book remaining seats.

## Tests on a dedicated test installation

Install development dependencies with `composer install` on the test host. Configure the existing MariaDB testing connection against a separate database whose name contains `test`; do not point the suite at production.

```bash
php artisan test --filter=AttendeePricingServiceTest
php artisan test --filter=PerAttendeePricingTest
php artisan test --filter=GroupBookingCapacityTest
php artisan test --filter=AppointmentTypeConfigurationTest
php artisan test --filter=QuestionnaireBookingFlowTest
php artisan test --filter=ShortNoticeFeeTest
php artisan test --filter=BladeCompilationTest
php artisan test
```

## Rollback

Before reverting to M7-R18 code or rolling back the migration, change every `per_attendee` appointment type to a supported earlier pricing mode. M7-R18 cannot interpret the new pricing mode. Rolling back drops the three new configuration columns; it does not delete existing bookings or their saved price lines. Keep a database backup if the attendee rules will need to be restored later.
