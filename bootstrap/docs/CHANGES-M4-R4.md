# M4 Revision 4

## Configurable maximum advance booking

- Replaced the hard-coded 365-day public booking horizon with appointment-type settings.
- Added `maximum_booking_notice_value` and `maximum_booking_notice_unit`.
- Supported units: minute, hour, day, week, month.
- A maximum value of `0` means there is no maximum advance-booking limit.
- Existing appointment types migrate to `365 days` to preserve M4-R3 behavior.
- Public slot generation and hold creation both enforce the maximum.
- The old `BOOKING_PUBLIC_HORIZON_DAYS` setting is no longer used.
