# Payments

M9 adds tenant-owned online payment collection. Appointment To coordinates checkout and records the ledger; each organization remains the merchant and supplies its own provider account.

## Provider setup

Owners and administrators configure providers under **Organization → Payments**.

- Stripe uses a secret API key and webhook signing secret. The client is redirected to Stripe-hosted Checkout.
- PayPal uses a client ID, client secret and Webhook ID. The client approves an Orders v2 order and Appointment To captures it server-side.
- Credentials use Laravel encrypted casts and are never redisplayed. Rotating credentials does not rewrite historical transactions.
- Test/live mode is chosen independently for each provider. At least one complete, enabled provider is required before a client can start checkout.

The settings page displays the organization-specific webhook URL and the events that must be subscribed. Webhook routes are exempt from CSRF but reject providers without complete verification credentials and require provider signature verification. Event IDs are unique per organization/provider, so delivery retries are idempotent.

## Collection terms

Every paid appointment type collects either:

- the full final booking price; or
- a fixed or percentage retainer, followed by a client-initiated balance payment.

The final price, initial amount due, balance due instant and cancellation refund percentages are copied to the booking after all attendee, questionnaire, distance, short-notice and ticket-seating calculations. Percentage arithmetic uses integer minor units and deterministic half-up rounding. Editing the appointment type later does not change an existing booking.

The balance due date is calculated in the organization timezone. It is displayed in the client and staff ledgers; M9 does not store payment credentials or make an off-session charge.

## Status and reconciliation

`pending_payment` is reached only after email, contract and staff prerequisites are complete. Capturing the exact initial amount and currency confirms the booking. With a retainer, confirmation can occur while `payment_status` remains `partially_paid`; the outstanding balance stays payable from the private booking page.

The initial payment window defaults to 60 minutes. The existing scheduled `appointments:expire-pending-bookings` command cancels an unpaid booking after that deadline and releases its ticket/capacity lifecycle. A provider capture delivered after expiry is still recorded and is automatically refunded in full.

Each checkout is an immutable `payment_transactions` row. Provider capture IDs, responses and lifecycle timestamps form the audit ledger. Completion validates the requested amount/currency, cancels other open local checkout attempts and is safe to process from both the browser return and webhook. A later duplicate capture is recorded and its excess is automatically refunded.

Ticketed bookings use the same workflow: tickets stay Reserved while payment is pending and become Valid when the booking is confirmed.

## Refunds

Appointment types define separate 0–100% policies for client and staff cancellations. Those values are snapshotted onto the booking. Technical duplicate/excess captures remain fully refundable and do not consume the policy refund on legitimate captures. Cancellation subtracts existing pending/successful allocations, allocates the remaining target across original captures and submits through the original provider.

Authorized staff may issue an additional amount from the booking ledger. A refund request reserves its allocation before the provider call so concurrent requests cannot exceed the unrefunded captured amount. Provider timeouts remain Pending and retain the same idempotency key for safe staff retry. Explicit provider rejection becomes Failed. A provider refund reference is required before completion, and out-of-order events cannot regress a successful refund. Successful refunds update the booking totals and payment status.

A capture received after cancellation is fully refunded. Refund and capture provider calls happen after the booking transaction commits, so network failures never roll back the cancellation itself.

### Refundable resource deposits (M9-R6)

Resource deposits are a distinct refundable part of the booking total. The final deposit is itemized and snapshotted per resource, including the quantity, effective per-unit amount, configuration source, question context, and currency. It is collected in full with the initial checkout even when the service price uses a retainer. An allowlist can waive service prepayment but never waives a deposit.

Deposits are added after service-price calculations. Questionnaire percentage charges and short-notice fees therefore do not use deposits as their basis, and coupons cannot discount them. Cancellation percentages apply only to captured service money; every captured deposit is returned in full on cancellation.

Managers can return all of the remaining collected deposit or a smaller amount. A partial return requires a reason. Deposit funds are reserved separately from ordinary manual refunds, and the refund is allocated only to successful transactions that captured the deposit. The Stripe payment intent or PayPal capture on that transaction is used, ensuring the refund follows the original payment method. Pending refunds reserve their amount and reuse the same provider idempotency key on retry.

## Gift cards and coupons (M9-R3)

Public gift-card/coupon purchases use the same organization-owned Stripe and PayPal accounts, return verification, signed webhooks, exact amount/currency checks and immutable transaction ledger. Purchased codes stay pending until capture succeeds. Manual codes are active immediately.

Issued codes snapshot a fixed value or percentage, optional expiry and appointment-type applicability. A fixed code can be partially redeemed and retains its remaining balance; a percentage code is consumed by its first booking. The discount is applied after all appointment/questionnaire/equipment/event price lines and before the booking's full-payment or retainer snapshot is calculated.

There is deliberately no buyer cancellation or refund endpoint. An owner or administrator can destroy only a never-redeemed code. A purchased code then receives one full, idempotent refund against its original capture; manual codes are simply marked destroyed. The code, destruction reason, payment and refund records remain available for audit.

## Allowlist and blocklist rules

Rules match either a normalized exact email address or its exact domain.

- Blocklist: reject final booking creation with a contact-the-organization message.
- Allowlist: retain the quoted price but waive the online prepayment requirement. The trusted client may still pay the balance from the private booking page.
- A matching blocklist rule always wins, including when an allowlist rule is more specific.

Rules are organization-scoped. Their effect is snapshotted onto the booking so disabling or deleting a rule does not retroactively place an existing booking back into `pending_payment`.

## Operational notes

- Keep the Laravel application key stable; changing it makes stored provider credentials unreadable.
- Use HTTPS for all public, return and webhook URLs.
- Do not expose provider secrets in environment-wide client code or logs.
- Re-delivering a webhook or retrying a Pending refund is expected and safe.
- Seating-fee changes during rescheduling remain rejected because booking prices are immutable once created.
