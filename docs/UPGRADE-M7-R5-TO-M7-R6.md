# Upgrade M7-R5 to M7-R6

M7-R6 is an additive upgrade. It preserves existing users, memberships, organizations, resources, appointments, bookings, calendar connections, and stored files.

1. Back up the MariaDB database, application `.env`, and runtime `storage`.
2. Apply `m7-r5-to-m7-r6.patch` to an M7-R5 source tree, or replace the application source with the M7-R6 full package while preserving `.env` and runtime `storage`.
3. Clear cached application state and run the additive migration:

```bash
php artisan optimize:clear
php artisan migrate
```

M7-R6 adds:

- `2026_08_27_000050_create_organization_holidays_table.php`

4. Run focused tests, followed by the complete suite:

```bash
php artisan test --filter=OrganizationHolidayAvailabilityTest
php artisan test --filter=CurrentOrganizationNavigationTest
php artisan test --filter=BootstrapResponsiveUiTest
php artisan test
```

5. Sign in as an owner, administrator, or manager. Open **Scheduling → Availability → Configure holidays**, add a holiday, and preview the date to confirm no slots are returned. Disable it and confirm normal availability returns.
6. With a user who has two active organization memberships, verify the navbar dropdown switches the active organization and remains usable in a narrow/mobile viewport.

No Composer install, new environment setting, queue change, cache flush beyond `optimize:clear`, or new scheduler entry is required.

Do not run `migrate:fresh` on an existing installation. Rolling back this migration drops configured holiday rules, so use rollback only before production holiday configuration is entered or after taking a database backup.
