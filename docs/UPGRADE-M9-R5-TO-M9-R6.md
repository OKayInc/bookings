# Upgrade M9-R5 to M9-R6

M9-R6 builds on the complete M9-R5 release. The database change is additive and no Composer or npm dependency is added.

1. Back up MariaDB, the application's `.env` (including `APP_KEY`), and runtime `storage`.
2. Deploy `bookings-M9-R6.zip`, preserving the live `.env`, runtime `storage`, and installed dependencies. Alternatively, apply `m9-r5-to-m9-r6.patch` from the root of an unmodified M9-R5 source tree:

   ```bash
   git apply --check /path/to/m9-r5-to-m9-r6.patch
   git apply /path/to/m9-r5-to-m9-r6.patch
   ```

3. Run the additive migration and clear cached state:

   ```bash
   php artisan migrate --force
   php artisan optimize:clear
   ```

4. Against a dedicated MariaDB test database whose name contains `test`, run:

   ```bash
   php artisan test --filter=M9R6ResourceDepositTest
   php artisan test --filter=M9R4ConditionalResourceRequirementTest
   php artisan test --filter=PaymentSupportTest
   php artisan test --filter=BladeCompilationTest
   php artisan test
   node --test tests/JavaScript/*.test.cjs
   ```

   Never run the test suite or `migrate:fresh` against production.

5. In a staging organization, verify a resource default, a blank inherited question override, an explicit-zero override, quantity multiplication, a retainer checkout, and both partial and full deposit returns. Confirm the provider ledger shows the refund on the original payment/capture.

`public/js/page-loader.js?v=m9-r6` is served directly and requires no asset build. The version query prevents browsers from reusing the earlier loader asset. See `VERIFICATION-M9-R6.md` for checks completed while packaging and the host checks still required.
