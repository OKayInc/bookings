# M9-R3 — ordered questionnaire choices and gift cards/coupons

## Questionnaire option order

- Checkbox, radio and select choices now expose an optional numeric **Order** field.
- A blank order is stored as `0`.
- Choices are rendered by ascending order, then by case-insensitive label; the original input sequence is no longer a hidden ordering rule.
- Reusable-question choices use the same deterministic ordering.

## Gift cards and coupons

- Owners and administrators can define public purchasable offers or issue an individual code manually under **Organization → Gift cards & coupons**.
- Benefits can be a fixed stored-value amount or a one-use percentage discount. Fixed cards retain an unused balance across bookings.
- Offers and issued codes may apply to every appointment type or an explicit selected set and may have an inclusive expiration date or no expiration.
- Public purchases use the organization's existing Stripe/PayPal credentials and immutable payment ledger. Each issued code snapshots its benefit, scope and expiry.
- Checkout accepts a code, applies its discount to the final price before payment terms are calculated, and records every redemption separately.
- Buyer-selected passwords protect all public coupon pages. Delivery supports printable pages, an emailed protected link, or an emailed standards-based QR SVG for that protected link. Passwords are never emailed.
- Buyers have no cancellation or refund action. An owner/administrator may destroy only a never-redeemed code. If it was purchased, destruction creates a full refund through its original provider capture; failed/pending provider refunds retain their idempotency key for safe retry.
- Destroyed and redeemed records are retained for audit rather than deleted from payment history.

## Compatibility

Migration `2026_09_03_000066_add_m9_r3_coupons.php` creates offer, issued-code, applicability and redemption tables. It also allows a payment/refund ledger row to belong to either a booking or a coupon purchase. Existing booking transactions remain unchanged.
