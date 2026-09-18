# Staff / Resource Confirmation

M6 converts the old `requires_resource_confirmation` flag into a complete workflow and snapshots the flag onto each booking so later appointment-type edits do not rewrite history.

## Resource snapshots

Confirmations are created from `appointment_resources`, not from the current appointment-type configuration. This is intentional: required/optional state was snapshotted when the appointment/session was reserved.

Only resources connected to a `Person` get response records. Email notifications are sent when that person has a primary email address; a missing email still leaves a required confirmation pending rather than silently approving it. Rooms/equipment still constrain availability when required but cannot click Accept/Decline.

## Required vs optional

- Required person-resource pending: booking remains `pending_staff_confirmation`.
- Required person-resource declined: booking becomes `declined`.
- All required person-resources accepted: booking progresses to `pending_payment` or `confirmed`.
- Optional person-resource pending/declined: does not block progression.

## Response channels

Staff can respond:

1. Authenticated backend: **My confirmations** or the booking detail page.
2. Private email link: opens a no-login confirmation page and then submits Accept/Decline.

The email GET link itself does not mutate state, which prevents mail scanners from accidentally accepting/declining an appointment.

## Rescheduling

A reschedule invalidates/deletes the old resource-confirmation set and generates fresh confirmations for the newly reserved appointment resources. Staff approval of the old time is never silently reused for a new time.
