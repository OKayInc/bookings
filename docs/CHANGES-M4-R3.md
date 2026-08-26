# M4 Revision 3

## Appointment booking notice

Each appointment type now has a configurable minimum advance-notice period:

- minutes
- hours
- days
- weeks
- calendar months

The configuration is stored as `booking_notice_value` plus `booking_notice_unit`. Existing appointment types migrate to `0 hours`, which means no minimum notice beyond M4's existing rule that appointments cannot start at or before the current instant.

Calendar units are calculated in the organization's IANA timezone. In particular, a month is a calendar month using `addMonthsNoOverflow()`, not a fixed 30-day approximation.

The notice period is enforced twice:

1. public availability omits slots that are too soon;
2. public hold acquisition rechecks the notice period server-side, preventing a client from bypassing it by submitting a start time manually.

Group-session joins are subject to the same notice period as newly created sessions.

No existing data is removed. Migration `2026_08_24_000026_add_booking_notice_to_appointment_types.php` is additive.
