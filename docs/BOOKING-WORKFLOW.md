# M4 booking workflow

## M7-R21 attendee-count comparisons

Numeric constraints may compare the submitted answer with the booking's reserved attendee count, including its primary client. The checkout page renders this count from the hold for browser feedback. Live quotes and final validation receive the count directly from the server-side hold, not request fields or answers. Other bookings in the same session, session capacity, and optional attendee-name entries do not change the operand. Existing AND/OR, hidden-question, and optional-answer behavior remains unchanged.

## M7-R20 numeric answer constraints

After resolving visible questions, the live quote checks any supplied constrained numeric answers. Final submission first runs ordinary required/type/min/max validation, then evaluates numeric constraints before distance lookups, pricing, or booking persistence. AND binds within each group; OR separates alternatives. An unanswered optional target is allowed, while a missing or hidden source fails its predicate rather than becoming zero. Hidden targets are ignored even if a client submits stale values. Client-side feedback uses the same decimal comparison semantics, but the server remains authoritative.

## M7-R19 attendee pricing

Per-attendee pricing is optional and restricted to group appointment types. Flat, absolute-range, and accumulative-range calculations use the attendee count of the individual booking, including its primary client. Other bookings in the same session do not move a client into a different price range. Group sessions remain shared: multiple clients may book remaining seats, subject to capacity and holds.

The slots response includes the base price for the selected count. Changing booking filters invalidates that preview. After a hold is acquired, both checkout quotes and booking creation use its stored count, never a client-submitted replacement count or total. Percentage questionnaire extras use the calculated attendee base or subtotal, fixed extras retain their existing once/per-unit rules, and short-notice fees apply after extras. Saved base-price lines include the unit rate and quantity for each charged portion. Existing bookings retain their price when appointment-type pricing changes.

## M7-R18 seasonal gate

An appointment type may define an inclusive one-time or yearly booking season in its organization's timezone. The season gate runs before a slot is offered and again during hold acquisition, final booking, and rescheduling. Existing group sessions are not offered for additional capacity after their date falls outside the current season configuration. Staff schedule-change proposals use the same availability/hold/reschedule services and therefore cannot bypass it.

The public appointment-type catalog hides a seasonal type while the current organization-local instant is off season. Direct access remains available so a client with a link can select a valid future season date.

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

## Online meetings

When an appointment type is online, creating a new appointment/session snapshots its selected provider and provisions one meeting for the shared appointment—not one meeting per attendee booking. Group bookings that join the same appointment reuse its existing meeting URL.

- Jitsi creates a unique, hard-to-guess room without provider credentials.
- A custom provider snapshots the organization's reusable URL onto the appointment.
- Google Meet, Microsoft Teams, Zoom, and Webex exchange the organization's encrypted credentials for short-lived access and call the provider meeting API.
- Attendee and host URLs are encrypted at rest. Only the passwordless booking-management page exposes the attendee URL; the host URL remains backend-only.
- Provider failure does not roll back an otherwise valid booking. Staff see the sanitized error and may retry after correcting organization settings.
- Rescheduling to a new appointment provisions that appointment's own snapshotted provider meeting.

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
- `pending_payment` (payment collection is M9)
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

## Short-notice fees

The minimum booking notice remains a hard availability rule. Separately, an appointment type may define fixed or percentage price tiers for starts that are still bookable but occur soon after the booking is made.

For each quote, the server calculates every threshold from the current instant in the organization's IANA timezone. If multiple tiers match, only the shortest matching threshold is charged; fees never stack. Exact threshold boundaries are inclusive. Calendar-month tiers use the same `addMonthsNoOverflow` behavior as booking notice.

Percentage tiers apply to the current subtotal after the base duration price and questionnaire extras. The complete quote is recalculated on final submission and persisted to `booking_price_lines` with the selected fee rule UUID and threshold metadata. Changing or deleting appointment-type fee rules later does not alter an existing booking's price snapshot.

## Address driving-distance fees

An address questionnaire question may use a private configured origin to calculate a driving route to the client's answer. The held-time quote calls Google Routes and adds either the configured fixed fee or the single matching kilometer/mile range. Final submission first validates the address, obtains the route authoritatively, and persists the result with the answer and price breakdown.

The origin is configuration only: it is never placed in guest HTML, quote JSON, booking answers, or booking price-line metadata. If Google cannot return a route, submission fails instead of creating an unpriced booking. Successful route lookups are cached briefly so the quote and immediate final submission normally reuse the same distance result.
