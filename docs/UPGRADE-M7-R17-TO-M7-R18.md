# Upgrade M7-R17 to M7-R18

M7-R18 is an additive source and database upgrade.

1. Back up MariaDB, `.env`, `APP_KEY`, and runtime `storage`.
2. Apply `m7-r17-to-m7-r18.patch` to an M7-R17 source tree, or replace the source with the M7-R18 package while preserving `.env` and runtime `storage`.
3. Install the existing locked dependencies, run the migration, and clear cached application artifacts:

```bash
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan optimize:clear
```

Do not run `migrate:fresh` on an existing installation.

4. Edit appointment types that should be seasonal, enable **Offer this appointment type only during a date range**, select start/end dates and choose one-time or yearly recurrence.
5. For a yearly season crossing New Year, configure a reference range such as `2026-11-15` through `2027-02-15`. The years establish the wrap; the month/day values repeat.
6. Run focused tests followed by the complete suite:

```bash
php artisan test --filter=SeasonalAppointmentTypeTest
php artisan test --filter=AppointmentTypeConfigurationTest
php artisan test --filter=PublicAppointmentTypeTest
php artisan test --filter=GuestBookingFlowTest
php artisan test --filter=StaffScheduleProposalTest
php artisan test
```

Rolling back removes only the seasonal configuration columns. Existing bookings and appointment types remain.
