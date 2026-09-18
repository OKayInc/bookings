# Upgrade M7-R13 to M7-R14

M7-R14 is a source-only availability and public slot-presentation upgrade.

1. Back up the MariaDB database, application `.env`, and runtime `storage`.
2. Apply `m7-r13-to-m7-r14.patch` to an M7-R13 source tree, or replace the source with the M7-R14 full package while preserving `.env` and runtime `storage`.
3. Install the existing locked dependencies and clear cached configuration, routes, and Blade views:

```bash
composer install --no-dev --optimize-autoloader
php artisan optimize:clear
```

No migration command is required when upgrading from M7-R13. Do not run `migrate:fresh`. M7-R14 adds no table, column, index, or Composer dependency.

4. Run focused tests followed by the full suite:

```bash
php artisan test --filter=AvailabilityEngineTest
php artisan test --filter=GuestBookingFlowTest
php artisan test --filter=RegionalHolidayAvailabilityTest
php artisan test --filter=ExternalCalendarAvailabilityTest
php artisan test
```

5. Verification scenario:

   - Configure an eight-hour appointment with a 15-minute start interval.
   - Configure one day as `18:00–23:59` and the following day as `00:00–02:00`.
   - Select the first date and confirm the slot box shows `6:00 PM – 2:00 AM`, without repeating the date.
   - Reserve the slot and confirm the hold starts on the selected date and ends at 02:00 the following date.
   - Add a following-day blackout, booking, resource closure, or external-calendar conflict and confirm the slot disappears.
   - Change the first rule to end at `23:45` and confirm the real 15-minute gap prevents the cross-midnight slot.
