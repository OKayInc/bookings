# Offline payments and post-appointment balance review

## Intended workflow

For a CAD 50.00 appointment with a CAD 20.00 retainer, a verified CAD 20.00 receipt satisfies the initial payment and leaves CAD 30.00 outstanding. The retainer is part of the appointment price, not an additional charge. Existing refundable resource deposits remain separate and are allocated using the existing payment ledger rules.

Enable offline payment from **Appointment types → Edit → Offline payments / e-Transfer settings**. Enter payment instructions and a reservation payment window. The default is 24 hours; minutes, hours and days are accepted, up to 30 days. Offline payment is disabled by default, so this feature does not silently change existing payment choices. Stripe and PayPal remain available independently.

The customer chooses **Pay offline / e-Transfer** on the existing secure booking-management page after the booking prerequisites permit payment. This starts and snapshots the type-specific deadline and instructions. The deadline is capped at the appointment start. Selecting again, refreshing, submitting a reference, or changing the appointment type settings never restarts an existing deadline. A booking whose previous online/default reservation window has already elapsed cannot acquire a new offline window.

The customer sends the transfer outside this application and submits its reference/confirmation number. This creates an **unverified claim**, not a successful payment. Privileged staff are notified through the existing scheduler. Open the booking's **Payment review** page, check your bank or other records, then record the amount actually received. A matching submitted reference is linked to the receipt. The same verified transfer reference cannot be counted twice within one organization.

Any positive, verified partial payment protects the offline reservation from its payment deadline, as requested. However, a CAD 5.00 receipt against a CAD 20.00 retainer does **not** falsely satisfy that retainer: the booking remains pending payment with CAD 15.00 initially outstanding. A CAD 20.00 receipt completes the retainer; a CAD 50.00 receipt settles the total. Staff can also record cash or other external payments without a transfer reference.

At the deadline, a reservation with no verified partial/full payment is cancelled. Its capacity is released; the session and external calendar events are cancelled only if no other active booking/hold still requires that session. The customer receives an explanation and instructions to contact the business if money was already sent. A late transfer may be recorded using the explicit late-payment reconciliation checkbox, but it does not restore the cancelled booking or take back a slot now held by someone else.

## After the appointment

The existing attendance-review email is extended with a separate payment review whenever a balance remains. Payment reminders are not suppressed by an already-recorded attendance outcome or by disabling optional attendance-review emails. The email links to the existing attendance screen when both reviews are needed, and to the authenticated payment-review page when only payment needs attention.

The staff payment page asks whether the appointment was paid and offers:

- Record the actual payment received, including the remaining CAD 30.00 in this example.
- Give more time: choose 1–365 additional days, notify the customer of the new deadline, and ask staff again after that deadline if money is still outstanding.
- Explicitly blacklist for non-payment: create a manual, organization-local blacklist entry with a non-payment reason and an audit record. Attendance and the outstanding debt are not changed. Extension expiry alone never blacklists anyone. Later payment does not silently remove a manual blacklist.

A queue is available at **Bookings → Offline payments and unpaid balances**. Each booking also links to its payment-review page. Future deferred dates remain visible in this queue; due reminders consume their notification trigger, while the extension history remains in the audit log.

Financial changes require an authenticated, active owner, administrator or manager of the booking's organization. A customer's manage token or the existing signed attendance link does not authorize receipts, refunds, extensions or blacklisting. Public financial mutations use POST and the existing web CSRF protection; recipient and organization authorization are rechecked on each staff request.

## Refunds

Offline receipts use the existing payment transaction ledger, so totals, reports, refunds and the remaining balance use verified money rather than customer claims. A manual receipt can legitimately cover both retainer and balance; the duplicate-online-initial-checkout refund detection excludes offline receipts.

Existing refund controls can create applicable refund requests. An offline refund remains **pending manual action**; neither Stripe nor PayPal is called. Send the refund outside the application, then use **Pending offline refunds** on the staff payment-review page to confirm that the money was actually returned. This updates the existing refund ledger. Recording completion never sends money. Ordinary refund/price semantics remain those of the existing application.

## Scheduling and multiple servers

The existing commands are reused:

```sh
php artisan appointments:expire-pending-bookings
php artisan appointments:send-outcome-reviews
php artisan appointments:sync-calendars
```

`appointments:expire-pending-bookings` now runs every minute in `routes/console.php`. It also delivers pending transfer-submission, extension and expiry notices. The existing outcome-review command remains scheduled every ten minutes. Calendar synchronization remains every five minutes and retries outstanding external-event deletions.

Keep the existing Laravel scheduler cron running; no new daemon or queue worker is introduced by this feature. With a functioning scheduler, expiry is processed on the next due run, not at the exact wall-clock microsecond. If you run the commands directly from cron instead of `schedule:run`, adjust the expiry command to run every minute; do not additionally duplicate it in the scheduler.

The payment/action state is stored in the shared database. Booking row locks, receipt/reference uniqueness and persisted notification markers prevent ordinary competing runs from recording the same receipt or repeatedly consuming the same review. Shared-cache scheduling locks alone are not assumed sufficient when each server uses its own Redis instance. SMTP/provider delivery and database commits cannot be made one atomic transaction: a crash after delivery but before marker commit can cause a duplicate email. Delivery errors are reported and unconsumed markers remain retryable.

First attendance/balance review emails retain the existing 48-hour appointment lookback to avoid a deployment-time flood of old messages. Explicit extensions are reviewed even when their appointments are older than 48 hours. Older unpaid bookings remain accessible through the WebUI. If the scheduler is offline for more than 48 hours, review that queue for appointments outside the initial email window.

## Accounting, compatibility and rollout

The migration is additive. Current bookings do not automatically acquire offline deadlines. Offline choice snapshots its own instructions/deadline, but it does not override existing email verification, contract, admission or staff-approval requirements.

Test with a separate database before production. Deploy the code to all web/cron nodes, run the shared database migration once, rebuild each node's cached configuration/views, and reload the PHP-FPM/FCGI handlers as appropriate for your installation. Do not serve mixed old/new code once offline ledger records exist: old code does not recognize the new `offline` provider enum. The migration refuses rollback when offline receipts exist; do not bypass that accounting safeguard.

```sh
# Development/staging only, with a correctly configured isolated test database:
php artisan config:clear
php artisan test --filter=OfflineBookingPaymentsTest
php artisan test
php artisan view:cache
php artisan route:cache

# Production, during your normal coordinated deployment:
php artisan migrate --force
php artisan optimize:clear
php artisan optimize
```

The regression test file is `tests/Feature/OfflineBookingPaymentsTest.php`. It contains 20 scenarios covering the example amounts, references, deadlines, duplicate receipts, partial payments, shared sessions, late money, authorization, review timing, extensions, blacklist provenance, settings, online-provider isolation and manual refund completion. These application tests must be run in the actual project; standalone PHP syntax checks are not a substitute for them.
