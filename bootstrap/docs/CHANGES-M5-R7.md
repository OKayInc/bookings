# M5 Revision 7 — Required and Optional Resources

M5-R7 adds two-level resource requirement configuration.

## Organization resource default

Each resource now has a default appointment requirement:

- Required
- Optional

Existing resources migrate as Required, preserving previous behavior.

## Appointment-type override

Each resource assigned to an appointment type can be configured as:

- Use organization default
- Required
- Optional

Existing appointment-type assignments migrate as `inherit`, which resolves to the resource's organization default.

## Scheduling behavior

Required resources participate in mandatory availability intersection. If any required resource is inactive or unavailable, the slot is not bookable.

Optional resources do not block slot generation. When a hold is acquired, available optional resources are reserved; unavailable optional resources are skipped.

## Historical snapshot

`booking_hold_resources` and `appointment_resources` now carry an `is_required` snapshot. This ensures later changes to resource configuration do not alter what was required for an already-created appointment and gives M6 a stable basis for staff confirmation.
