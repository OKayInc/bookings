# M9-R6 — refundable resource deposits

M9-R6 adds refundable deposits to resource rentals and keeps those funds distinct throughout pricing, checkout, refunding, and audit history. Equipment resources continue to support bookings without a linked person.

## Configuration and fallback

The resource editor has an optional default deposit in the active organization's currency. Quantity-tracked equipment treats it as a per-piece amount.

Conditional resource requirements on choice questions have a deposit override beside every resource. The effective value is resolved as follows:

| Question assignment | Resource default | Effective deposit |
| --- | --- | --- |
| Positive amount | Any value | Assignment amount |
| Explicit `0` | Any value | Zero |
| Blank | Positive amount | Resource amount |
| Blank | Blank or zero | Zero |

All values are stored as integer minor units. Existing records migrate to no deposit.

## Pricing and snapshots

The effective deposit appears as its own refundable quote line. Quantity-managed resources multiply the per-piece amount by the held quantity. Permanent and conditional all-resource groups charge every required item; a one-of group charges once using the highest effective candidate amount so any eventual replacement is covered.

Deposits are added after service fees and cannot be reduced by coupons. The full deposit is due in the initial checkout on top of any service retainer. A service-prepayment allowlist does not waive it.

Each completed booking snapshots its deposit total and itemized resource context. Later edits to resource or question configuration cannot alter that booking.

## Returns and refunds

The staff booking ledger shows original, successfully refunded, and remaining deposit amounts. Managers may choose **Refund all remaining** or **Partial refund**. Partial refunds expose and require an audit reason.

Refund allocations are bound to the successful payment transaction that captured the deposit, so Stripe refunds the original payment intent and PayPal refunds the original capture. Pending allocations prevent over-refunding and keep their idempotency key for safe retry. Ordinary price refunds cannot consume reserved deposit funds. Cancellation refunds return captured deposits in full while applying the configured percentage only to service funds.

## Page loading feedback

Both authenticated and public layouts now include a lightweight loading overlay for normal same-origin navigation and form submission. New-tab, modified, download, and same-page anchor navigation are ignored. The overlay clears on load and browser history restoration and uses reduced-motion-aware animation.
