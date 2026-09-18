# M7-R9 changes

M7-R9 adds named replacement resource groups: a required 1-of-N resource rule that is enforced consistently by availability, booking holds, appointment snapshots, and staff confirmation.

## Appointment-type configuration

- Every assigned resource now offers **One of a replacement group** as a requirement mode.
- Each replacement candidate receives a group name. Names are whitespace-normalized and matched case-insensitively when the form is saved.
- A group must contain at least two selected resources. A missing group name or a one-resource group is rejected by server-side validation.
- Existing `inherit`, `required`, and `optional` assignments are unchanged.

## Availability and holds

- Standalone required resources continue to intersect availability.
- Each replacement group contributes a union: a slot survives while at least one member is available.
- A candidate's active state, effective schedule, internal holds/appointments, connected-calendar busy time, organization holidays, and per-resource regional holidays are evaluated independently.
- Simultaneous appointments of the same type may use different members of a replacement group.
- Hold acquisition rechecks connected calendars and snapshots every candidate that is still available. If no candidate remains in any group, acquisition fails atomically.
- Booking creation and rescheduling recheck the snapshotted group so one candidate becoming closed or externally busy does not reject the booking while another remains usable.

## Staff confirmation and selection

- Every available person-resource candidate receives a confirmation request.
- A decline does not reject the booking while another candidate in the group is pending or accepted.
- The first acceptance selects that resource for the scheduled appointment, satisfies the group, marks other pending confirmations **Not needed**, and detaches non-selected candidates so their schedules and writable calendars are released.
- If all candidates decline, the booking follows the existing declined-booking lifecycle.
- Group selection is serialized with database row locks so concurrent responses cannot select two replacements.
- For a later booking that joins an already-selected group appointment, the existing accepted selection satisfies the group without sending a duplicate confirmation request; the selected person still receives the normal booking-assignment notification.

## Schema and deployment

Migration `2026_08_28_000052_add_replacement_resource_groups.php` adds a nullable `replacement_group` snapshot to `appointment_type_resources`, `booking_hold_resources`, `appointment_resources`, and `resource_confirmations`. Existing rows remain `NULL` and preserve their previous semantics. No Composer dependency, environment variable, queue change, or scheduled command is added.
