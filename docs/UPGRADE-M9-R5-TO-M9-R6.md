# Upgrade M9-R5 to M9-R6

M9-R6 builds on the full M9-R5 release. It adds the shared page-loading overlay and related request-path optimizations. There is no database migration or new dependency.

1. Back up MariaDB, the application's `.env` (including `APP_KEY`), and runtime `storage`.
2. Deploy `bookings-M9-R6.zip`, preserving the live `.env`, runtime `storage`, and installed dependencies. Alternatively, apply `m9-r5-to-m9-r6.patch` from the root of an unmodified M9-R5 source tree:

   ```bash
   git apply --check /path/to/m9-r5-to-m9-r6.patch
   git apply /path/to/m9-r5-to-m9-r6.patch
   ```

3. Clear cached application state:

   ```bash
   php artisan optimize:clear
   ```

4. Run verification against a dedicated MariaDB test database whose name contains `test`, following the existing `.env.testing.example` setup:

   ```bash
   php artisan test --filter=M9R6PageLoaderTest
   php artisan test --filter=M9R5DashboardTest
   php artisan test --filter=BladeCompilationTest
   php artisan test
   node --test tests/JavaScript/*.test.cjs
   ```

   Do not run the test suite against production. No migration is added by M9-R6; do not reset or rebuild a live database.

5. Check an authenticated page and a public booking page. Follow an internal link and submit a valid form; confirm the overlay appears and the destination replaces it. Confirm that cancelling a destructive-action prompt, opening a link in a new tab, following a same-page fragment, and downloading a contract do not leave the overlay visible. Use browser Back to confirm a restored page is interactive.

No asset build is required. Ensure `public/js/page-loader.js`, the updated `public/css/app.css`, and `resources/views/layouts/partials/page-loader.blade.php` are deployed.
