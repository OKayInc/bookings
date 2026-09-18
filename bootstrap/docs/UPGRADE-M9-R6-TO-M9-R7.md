# Upgrade M9-R6 to M9-R7

Back up the database and deploy this source over M9-R6, preserving your .env and storage files.

```bash
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan optimize:clear
php artisan optimize
```

The migration resets Person resource defaults and question assignment overrides to zero. A MariaDB CHECK constraint rejects nonzero or NULL Person defaults even through direct SQL. Model saves normalize values to zero, including conversion to Person. Existing booking/payment/refund snapshots remain unchanged. Rollback removes the constraint but does not restore invalid deposits.

Resource create/edit hides and disables deposits for Person, clearing the browser value on selection. Question assignments omit Person deposit controls; their server-side sync forces zero. Pricing ignores Person resources even if stale defaults or overrides are loaded. Other resource types retain existing behavior.

## Verification

All 67 JavaScript tests passed (including five new Person deposit scenarios). PHP, Composer, and MariaDB are unavailable in the packaging environment; PHP syntax, Blade compilation, migration, and Laravel tests have not been executed.

On staging with development dependencies installed:

```bash
php artisan test --filter='M9R7PersonDepositTest|M9R6ResourceDepositTest|M9R4ConditionalResourceRequirementTest|BladeCompilationTest'
php artisan test
node --test tests/JavaScript/*.test.cjs
```

Verify creation and editing of Person resources, Equipment-to-Person conversion, question assignment fields, and a deposit-bearing equipment booking. Verify the new database constraint using MariaDB 10.11.
