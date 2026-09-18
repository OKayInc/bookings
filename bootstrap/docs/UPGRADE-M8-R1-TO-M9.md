# Upgrade M8-R1 → M9

M9 is an additive database and application upgrade. Back up the database and application key before deployment. Do not use `migrate:fresh` on an existing installation.

```bash
php artisan down
php artisan optimize:clear
php artisan migrate --force
php artisan optimize
php artisan up
```

Then run the verification suite against the dedicated MariaDB test database:

```bash
php artisan test
./vendor/bin/pint --test
```

## Provider activation

For each organization that will accept payment:

1. Open **Organization → Payments**.
2. Enter Stripe and/or PayPal merchant credentials and select test/sandbox mode while validating.
3. Register the displayed webhook URL in that provider account.
4. Subscribe to the completion, failure/expiry and refund events listed on the page.
5. Copy the PayPal Webhook ID into the organization settings.
6. Complete a provider test payment, balance payment, cancellation refund and manual refund before enabling live credentials.

The application-level provider timeout, checkout TTL and initial-payment reservation window may be overridden with `PAYMENT_REQUEST_TIMEOUT_SECONDS`, `PAYMENT_CHECKOUT_TTL_MINUTES` and `PAYMENT_BOOKING_WINDOW_MINUTES`. Keep the existing Laravel Scheduler running every minute so expired unpaid bookings are released. Provider credentials are not environment variables; they belong to each organization and are encrypted with `APP_KEY`.

## Existing data

- Existing paid bookings remain `pending_payment` until a capture is recorded; M9 does not invent historical provider transactions.
- Existing free bookings are backfilled to payment status Paid.
- Existing appointment types collect the full amount unless an administrator explicitly configures a retainer.
- Existing refund defaults are 0% for client cancellation and 100% for staff cancellation. Review those settings before accepting live payments.
