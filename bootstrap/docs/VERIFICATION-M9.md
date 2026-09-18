# M9 verification

## Automated coverage added

- Provider credentials encrypt at rest.
- Blocklist precedence and allowlist matching.
- Deterministic percentage-retainer and balance-due snapshots.
- Exact provider amount/currency validation and booking confirmation.
- Organization-specific Stripe and PayPal checkout creation and stale-checkout replacement.
- Stripe webhook signature verification and initial-payment-window expiry.
- Empty webhook-verification configurations are rejected before payload processing.
- Serialized, idempotent automatic cancellation refunds.
- Duplicate/excess refunds remain separate from cancellation-policy entitlement.
- Successful refunds are terminal under out-of-order provider events.
- Paid ticket transition from Reserved to Valid.

The existing suite also exercises booking prerequisite ordering, policy cancellation origins, ticket lifecycle, tenant isolation, money parsing and Blade compilation used by M9.

## Workspace verification

- PHP grammar parse: 468 files, 0 failures.
- JavaScript tests: 53 passed, 0 failed.
- `git diff --check`: clean.

The workspace does not provide PHP, Composer, MariaDB or Memcached runtimes, so the Laravel/PHPUnit and Pint commands below must run on the application host or CI before deployment.

## Required host verification

```bash
php artisan optimize:clear
php artisan test
./vendor/bin/pint --test
php artisan route:list --path=payment
```

Use provider test/sandbox accounts to verify browser redirects and webhook delivery because faked HTTP tests intentionally do not contact merchant systems. Confirm the provider dashboard amount/currency and the Appointment To ledger agree for initial payment, balance, cancellation refund and a deliberately retried webhook.
