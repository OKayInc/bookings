# M6-R2 changes

M6-R2 builds on M6-R1 and adds staff-initiated schedule-change proposals.

## Added

- `booking_schedule_proposals` history table.
- `ScheduleProposalStatus` enum (`pending`, `accepted`, `kept_original`, `cancelled`, `expired`, `withdrawn`).
- Staff proposal UI on the backend booking page.
- Alternative-time availability endpoint for assigned staff/managers.
- Alternative slots held with the existing MariaDB booking-hold engine.
- Private client proposal links delivered by email.
- Proposal controls on the existing passwordless Manage Booking page.
- Client actions: Accept proposed time, Keep original time, Cancel booking.
- Proposal expiration and withdrawal.
- Active staff availability warnings after Keep Original or expiry.
- Staff warnings in client/resource reminder emails.
- `cancellation_origin` on bookings, including `staff_schedule_change` for future refund logic.
- `appointments:expire-schedule-proposals` scheduled command.
- Proposal TTL settings in `config/booking.php` and `.env.example`.
- Assigned-resource access for proposal creation even when the appointment does not require staff confirmation.
- Regression coverage for create/accept/keep/cancel/expiry and confirmation reset after an accepted proposal.

## Behavioural details

- A staff proposal does not move the current appointment until accepted.
- Normal new-booking minimum/maximum notice is bypassed for staff proposals, but resource availability/capacity/buffers remain enforced.
- Staff-initiated accepted changes do not increment the client's `reschedule_count`.
- Accepting a proposal clears previous schedule-warning flags and resets staff confirmation for the new time.
- Normal client rescheduling is temporarily unavailable while a staff proposal is pending, preventing conflicting schedule changes.
- Any booking cancellation releases pending proposal holds.
- Internal availability reasons are not exposed to clients; use `client_message` for client-visible details.
