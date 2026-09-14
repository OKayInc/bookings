# Upgrade M9-R8 to M9-R9

Back up MariaDB and the application files, then deploy the M9-R9 source while preserving the live `.env`, installed dependencies, and runtime storage.

Run:

```bash
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan optimize:clear
php artisan optimize
```

The additive migration creates organization and booking tax-line tables, adds future tax settings to organizations, and adds immutable tax snapshot columns to bookings. Every existing organization defaults to tax collection off. Every existing booking is backfilled with its current final price as its pre-tax subtotal and zero tax; no historical price or payment transaction is changed.

After deployment, edit each organization that must collect tax:

1. Enable **Collect taxes on bookings**.
2. Enter the organization's tax ID or registration number.
3. Choose whether advertised prices include tax or receive tax after the subtotal.
4. Add each required tax name and percentage, then save.

Run focused and complete verification on the staging/test database:

```bash
php artisan test --filter=OrganizationTaxTest
php artisan test --filter=BladeCompilationTest
php artisan test
node --test tests/JavaScript/*.test.cjs
```

Verify one tax-exclusive and one tax-inclusive checkout manually. Confirm the subtotal, individual taxes, final total, tax ID, hosted payment amount, client booking page, and staff booking page. Also verify a booking with a refundable deposit and one with a coupon.
