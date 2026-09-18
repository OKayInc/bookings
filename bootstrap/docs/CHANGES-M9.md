# M9 — payments

## Added

- Organization-specific encrypted Stripe and PayPal configuration with test/live selection and default-provider ordering.
- Stripe-hosted Checkout Sessions and PayPal Orders v2 approval/capture.
- Signed, organization-scoped and idempotent webhook ingestion.
- Full-price or fixed/percentage retainer configuration on appointment types.
- Booking snapshots for initial payment, balance due date, refund terms, exemptions and totals.
- Private client checkout for the initial amount and remaining balance.
- Configurable initial-payment window with scheduled expiry and capacity/ticket release.
- Immutable provider transaction, refund and webhook-event ledgers.
- Automatic client/staff cancellation refunds and authorized manual refunds.
- Pending refund retry with provider idempotency-key reuse.
- Late-capture and duplicate/overpayment automatic refund protection.
- Exact-email/domain allowlist and blocklist rules with blocklist precedence.
- Payment status and history in client and staff booking views.
- Paid ticket transition from Reserved to Valid after the required initial capture.

## Compatibility

The migration is additive. Existing paid bookings receive their full price as the initial amount due; free bookings are marked paid. Existing appointment types default to full collection, a 0% client cancellation refund and a 100% staff cancellation refund. No global merchant credentials are inferred or enabled.

M9 adds no Composer package, queue worker or scheduled command. Provider HTTP calls use Laravel's HTTP client. Existing booking prices, policies and tickets are not rewritten.

## Test-suite corrections

- Blocklist priority now explicitly replaces the payment-rule relationship's display ordering.
- Stripe signature tolerance uses Carbon's application clock so production and test-time verification share one clock source.
- Payment Blade views use block-form PHP directives, and MariaDB aggregate assertions normalize numeric strings.
