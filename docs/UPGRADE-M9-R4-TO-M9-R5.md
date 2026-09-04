# Upgrade M9-R4 to M9-R5

M9-R5 builds on the full M9-R4 release. It adds the dashboard list and has no new database migration or Composer dependency.

1. Back up MariaDB, the application's `.env` (including `APP_KEY`), and runtime `storage`.
2. Deploy `bookings-M9-R5.zip`, preserving the live `.env`, runtime `storage`, and installed dependencies. Alternatively, apply `m9-r4-to-m9-r5.patch` from the root of an unmodified M9-R4 source tree:

   ```bash
   git apply --check /path/to/m9-r4-to-m9-r5.patch
   git apply /path/to/m9-r4-to-m9-r5.patch
   ```

3. Clear cached application state:

   ```bash
   php artisan optimize:clear
   ```

4. Run verification against a dedicated MariaDB test database whose name contains `test`, following the existing `.env.testing.example` setup:

   ```bash
   php artisan test --filter=M9R5DashboardTest
   php artisan test --filter=BladeCompilationTest
   php artisan test
   ```

   Do not run the test suite against production. No migration is added by M9-R5; do not reset or rebuild a live database.

5. Log in and open `/dashboard`. Check the date and count controls, both status columns, pagination, and a booking-detail link. Confirm the employee view with a resource assigned to that employee. For an overnight event, confirm both dates appear.

The new JavaScript file is `public/js/dashboard-filters.js`; include it when deploying source files. No asset build is required. The Apply button provides a no-JavaScript fallback.

The PHP/MariaDB tests were added but could not be executed in the packaging workspace. See `VERIFICATION-M9-R5.md` for the completed and outstanding checks.
