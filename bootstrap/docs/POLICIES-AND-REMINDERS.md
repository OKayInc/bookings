# Cancellation, Rescheduling and Reminders

## Policy snapshotting

Appointment types define client policies, but every booking stores a snapshot of those values. Existing bookings therefore retain the rules accepted at booking time.

### Cancellation

Configuration:

- allowed / not allowed
- notice value + unit
- optional client-facing policy text

A notice value of `0` means the client may cancel until the appointment starts.

### Rescheduling

Configuration:

- allowed / not allowed
- notice value + unit
- maximum reschedules (`0` = unlimited)
- optional client-facing policy text

Client rescheduling uses the same availability/hold engine as a new booking, moves the booking to the new appointment/session, records the old/new times, and re-runs staff confirmation.

## Reminder rules

Each appointment type can enable one reminder rule.

Threshold basis:

- `lead_time`: only bookings created at least N days before the appointment qualify.
- `duration`: only appointments lasting at least N days qualify.

Then configure how long before the appointment the reminder should be sent (minimum 1 unit) and whether clients, resources, or both receive it.

`reminder_deliveries` guarantees idempotency.
