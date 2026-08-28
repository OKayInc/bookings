# M4 booking workflow

## Backend identity vs guest identity

Backend access continues to use:

`User → Person → OrganizationMembership → role`

Guests do not receive a `users` row. They use an organization-scoped `organization_contacts` record and a booking snapshot of their submitted contact details.

## Session vs booking

M4 deliberately separates:

`AppointmentType → Appointment (scheduled session) → Booking(s) → Attendee(s)`

A one-to-one appointment has capacity 1. A group/class appointment can have several independent bookings against the same scheduled session until the session capacity is full.

## Public flow

1. Client opens an authorized appointment-type page.
2. Browser detects an IANA timezone; client can change it.
3. Client chooses date, duration and attendee count.
4. Slot API intersects M3 schedules/resources/exceptions/buffers and existing sessions/holds.
5. Client selects a time.
6. MariaDB transaction creates a short-lived booking hold.
7. Client enters contact information; no registration/password is created.
8. If a contract exists, the hold snapshots the exact contract-template version. Client downloads that version and uploads signed PDF or page images.
9. Final booking transaction creates or joins an appointment/session, creates the booking/contact/attendees, snapshots price/currency, consumes the hold, and records invitation usage.
10. Email verification, contract review, staff confirmation and future payment requirements determine booking status.

## Replacement resources

An appointment type may assign two or more resources to the same named replacement group. Each group is a 1-of-N requirement:

- a slot is offered when at least one candidate is active and available under its own schedule, busy periods, calendar connections, and regional holidays;
- a hold reserves every candidate that is available at that moment, preserving a usable fallback while staff respond;
- one decline leaves the booking pending while another candidate remains pending;
- the first acceptance satisfies the group, keeps that resource on the appointment, marks other pending confirmations **Not needed**, and releases the non-selected appointment resources;
- the booking is declined only when every confirmation candidate in a required group has declined;
- standalone required resources still all need to be available and, when enabled, confirm independently.

The group name is snapshotted from appointment-type configuration through the hold, appointment, and confirmation rows so later configuration edits do not rewrite an in-flight or historical booking.

## Passwordless access

The database stores only SHA-256 hashes of booking-management and email-verification tokens. Raw tokens are sent in email URLs and are never persisted.

## Current M4 booking statuses

- `pending_email_verification`
- `pending_contract_review`
- `pending_staff_confirmation` (the staff approval UI is M6)
- `pending_payment` (payment collection is M8)
- `confirmed`
- `cancelled`
- `declined`

## Contracts

Contract templates and signed submissions use private storage. Staff/client downloads pass through authorized Laravel routes. Historical templates are retained and bookings point to the exact template version presented at booking time.

A signed submission is either:

- one PDF, or
- one or more JPEG/PNG/WebP page images.

Staff can approve or reject manually. Rejection generates a new passwordless management link so the client can upload a replacement submission.

## Expiration

Temporary holds expire through `appointments:expire-holds`.

Unverified bookings expire through `appointments:expire-pending-bookings`. When an appointment/session has neither active bookings nor active holds, M4 cancels that orphaned session so its resource time becomes available again.

## Minimum booking notice

Each appointment type may require advance notice before a guest can reserve a start time. The setting is stored as a numeric value and unit (`minute`, `hour`, `day`, `week`, or `month`). A value of `0` means no minimum notice other than the normal rule that a start time must still be in the future.

The public availability service removes starts that do not satisfy the notice period, and `PublicBookingHoldService` checks the same rule again while acquiring the hold. This keeps the rule authoritative even if a client submits a start timestamp manually.

Month-based notice uses calendar arithmetic in the organization's IANA timezone (`addMonthsNoOverflow`) rather than a fixed number of days.
