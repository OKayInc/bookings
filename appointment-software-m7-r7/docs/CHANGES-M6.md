# M6 changes

- Snapshotted `requires_resource_confirmation` onto bookings and added `resource_confirmations` with required/optional snapshots, private response tokens, response notes and audit timestamps.
- Added backend and private email-link staff Accept/Decline flows.
- Added **My confirmations** backend page.
- Added client notification when required confirmation resolves to confirmed/declined.
- Added cancellation and rescheduling configuration to appointment types.
- Added booking-level policy snapshots.
- Added passwordless client cancellation/rescheduling controls.
- Added reschedule history and fresh resource confirmation after schedule changes.
- Added appointment reminder configuration and idempotent reminder deliveries.
- Added `appointments:sync-staff-confirmations` and `appointments:send-reminders` commands.
- Added M6 regression tests for confirmations, policies/rescheduling, and reminders.
