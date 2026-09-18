# M7-R18 changes

M7-R18 adds optional seasonal availability to appointment types.

## Appointment-type configuration

- **Offer this appointment type only during a date range** enables a booking season.
- The season has an inclusive start date and inclusive end date in the organization's IANA timezone.
- **One time only** uses the configured dates exactly.
- **Repeat every year** projects the configured month/day window into each year.
- A recurring season may cross New Year by selecting its end date in the following calendar year.
- A yearly season must be shorter than one year; invalid or incomplete seasonal configuration fails closed.
- February 29 boundaries are clamped to the last valid day of February in non-leap years.

## Public and booking behavior

- A seasonal appointment type is listed in the organization's public appointment catalog only while the current organization-local date is inside its season.
- Direct, password-protected, unlisted, and invitation URLs remain usable outside the current season so clients can book valid future in-season dates.
- Slot generation requires the complete appointment interval to fit inside the season. A session may end exactly at midnight after the inclusive final date but may not consume time beyond it.
- Group-capacity slots outside the current configuration are suppressed.
- Hold creation, final booking, rescheduling, and staff schedule-change proposals revalidate the season server-side.
- If an administrator changes the season after a hold was created, that stale hold cannot create or move a booking outside the new season.
- Multi-day recurring schedule expansion no longer lets a rule's local end time overwrite the overall evaluation boundary.

## Schema

Migration `2026_08_30_000057_add_seasonal_availability_to_appointment_types.php` adds:

- `seasonal_availability_enabled`
- `season_start_date`
- `season_end_date`
- `season_recurrence` (`once` or `yearly`)

Existing appointment types remain year-round.
