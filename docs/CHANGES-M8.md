# M8 — ticketed events

## Added

- Optional ticket flag on appointment types, requiring group attendance and fixed duration.
- Doors-open semantics for the existing booking start plus required show start and optional show end inside the booking range.
- Event-level timing and seating snapshots on `appointments`.
- Unassigned, consecutive, section + seat, row + seat, and section + row + seat numbering.
- Optional seat component for section + seat and row + seat blocks, with explicit unnumbered quantities.
- Automatic collision-safe seat allocation inside the booking transaction.
- One unique ticket per attendee with Reserved, Valid, Checked in and Voided lifecycle states.
- Print-friendly private ticket pages and dependency-free Code 128 SVG barcodes.
- Organization-scoped check-in desk, duplicate-scan rejection, recent activity and undo.
- Ticket visibility in client booking management and backend booking details.
- Seat release on cancellation/decline and seat reassignment on pre-admission rescheduling.
- Seat release when an unverified guest booking expires.
- Guard against changing ticket timing, duration, capacity or seating while future booked event snapshots exist.

## Compatibility

Ticketing is disabled by default. Existing appointment types, appointments, bookings and attendees are unchanged. The migration is additive and uses existing tenant deletion cascades. Paid bookings continue to use `pending_payment`; M8 does not collect money.
