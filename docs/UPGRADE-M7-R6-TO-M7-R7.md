# Upgrade M7-R6 to M7-R7

M7-R7 is additive. It preserves existing users, organizations, memberships, holiday rules, shared resources, appointments, bookings, calendar connections, and stored files.

1. Back up the MariaDB database, application `.env`, and runtime `storage`.
2. Apply `m7-r6-to-m7-r7.patch` to an M7-R6 source tree, or replace the source with the M7-R7 full package while preserving `.env` and runtime `storage`.
3. Install the new Yasumi dependency, clear cached application state, and run the additive migration:

```bash
composer install --no-dev --optimize-autoloader
php artisan optimize:clear
php artisan migrate
```

M7-R7 adds:

- `2026_08_28_000051_add_regional_holiday_settings.php`
- Composer requirement `azuyalabs/yasumi:^2.11`

4. Run focused tests followed by the complete suite:

```bash
php artisan test --filter=RegionalHolidayAvailabilityTest
php artisan test --filter=OrganizationHolidayAvailabilityTest
php artisan test --filter=SharedResourceTest
php artisan test --filter=ResourceRequirementTest
php artisan test
```

5. Open **Scheduling → Availability → Configure holidays**. Confirm the timezone suggestion, save the intended country/subdivision, add a holiday, and verify it disappears from the select.
6. Edit two person resources in different regions, enable their holiday calendars, assign both as required to a test appointment type, and verify each country's holiday removes availability.
7. Repeat with an optional resource and verify its holiday does not remove the slot but the resource is not attached to the hold.

Existing organization closures retain their original behavior. Existing resources start with resource holiday enforcement disabled, so deploying the migration does not remove any current availability.

Do not run `migrate:fresh` on an existing installation. Rolling back drops saved region/enforcement settings and regional provider identifiers; take a database backup before rollback.
