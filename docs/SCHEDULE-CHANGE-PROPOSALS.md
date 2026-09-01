# Staff-initiated schedule-change proposals — M6-R2

M6-R2 adds a schedule-change negotiation workflow for cases where staff can no longer honour the currently booked time but can offer an alternative.

## Core rule

Sending a proposal **does not move the booking**. The current appointment remains the authoritative booking until the client explicitly accepts the proposed time.

The alternative slot is protected by a normal MariaDB-backed `booking_hold`, so another client cannot take it while the proposal is pending.

## Staff workflow

From the backend booking page, an authorized manager or a person-resource assigned to the appointment can:

1. choose an alternative date and timezone;
2. load times that satisfy current availability for required resources;
3. select a time;
4. select a proposal expiry (default 24 hours, maximum 168 hours by default);
5. record an internal availability reason;
6. write a separate client-facing message;
7. send the proposal.

The appointment type's normal minimum/maximum **new booking notice** is intentionally not enforced for a staff proposal. This allows staff to propose, for example, tomorrow even when new clients normally need one month of notice. Resource availability, buffers, capacity and active holds are still enforced.

Only one non-expired pending proposal is allowed per booking at a time.

## Client choices

The client can respond either from the existing passwordless Manage Booking page or from the private proposal link delivered by email.

### Accept proposed time

- the held alternative slot is converted into the booking's new appointment;
- the original appointment is released if it becomes orphaned;
- previous staff confirmations are invalidated;
- required person-resources must confirm the new time again;
- the booking returns to `pending_staff_confirmation` when required confirmations are needed, otherwise it returns to the appropriate workflow state (for example `pending_payment`);
- the staff-initiated move does **not** consume the client's maximum reschedule count.

### Keep original time

- the original booking remains unchanged;
- the alternative hold is released;
- the proposal becomes `kept_original`;
- an active staff-availability warning is displayed in the backend and client Manage Booking page;
- reminders include an availability warning.

### Cancel booking

- cancellation is allowed even if the normal client cancellation deadline has passed or client cancellation is disabled, because the cancellation was caused by a staff availability issue;
- the booking records `cancellation_origin = staff_schedule_change`;
- the proposal becomes `cancelled` and the alternative hold is released;
- M9 can use the cancellation origin/proposal history when deciding the applicable refund.

M6-R2 does not process payments/refunds yet.

## Proposal expiry

Pending proposals are expired by:

```bash
php artisan appointments:expire-schedule-proposals
```

Laravel Scheduler runs this every ten minutes. The alternative hold also has its own expiration and the ordinary hold cleanup continues every minute.

A proposal is never allowed to remain valid past the proposed appointment start. If the requested response window would extend that far, M6-R2 caps expiry to just before the proposed start.

When a proposal expires:

- its alternative hold is released;
- the original appointment remains scheduled;
- the proposal becomes `expired`;
- an active staff availability warning remains until the issue is resolved, the booking is moved, or the booking is cancelled.

## Withdrawing a proposal

The proposal's creator or a scheduling manager can withdraw a pending proposal. This releases the held alternative time and informs the client that the original booking remains unchanged.

## Historical integrity

`booking_schedule_proposals` is append/history oriented. Responded proposals are not deleted. It stores:

- original appointment/time snapshot;
- proposed time;
- proposer;
- internal reason;
- client-facing message;
- result/status;
- expiry and response timestamps;
- whether a staff availability warning remains active.

Accepted changes also continue to create the existing `booking_reschedules` history record.

## Privacy

`reason` is an internal staff availability note and is shown only in the backend. `client_message` is the text that may be shown to the client and included in the proposal email.
