# M7-R6 changes

M7-R6 adds optional organization holiday closures and a faster responsive organization switcher.

## Holiday closures

- Owners, administrators, and managers can open **Scheduling → Availability → Configure holidays**.
- No holiday is active by default. Each organization explicitly chooses the dates it honors.
- Common presets include fixed dates, Canadian/Ontario nth-weekday rules, and Easter-relative dates such as Good Friday, Easter Sunday, and Easter Monday.
- Custom closures can use an annual fixed date, an offset from Gregorian Easter Sunday, an nth weekday of a month, or a one-time date.
- Configured closures can be disabled and later re-enabled without losing their rule.
- Presets are conveniences, not a complete or legally authoritative statutory-holiday calendar.

An active holiday closes the entire organization from local midnight to the next local midnight in the organization's IANA timezone. It overrides weekly hours, resource/appointment-type custom schedules, and extra-availability exceptions.

Holiday rejection is part of authoritative availability calculation, group-capacity availability, booking-hold acquisition, and final hold consumption for booking/rescheduling. Existing appointments are retained if an administrator later adds a closure for their date, but an existing group session cannot accept more bookings while its date is closed. The closure does not cancel historical or current records.

## Organization switcher

- A user with more than one active organization membership sees a dropdown on the right side of the desktop navbar.
- The current organization remains visible and all other active memberships can be selected with one action.
- On mobile, the switcher stays inside the collapsed navigation, uses the available width, and scrolls when the list is long.
- The existing durable active-organization selection remains authoritative after a switch.
- Suspended memberships are not offered.

## Permissions and operations

Holiday management uses the existing scheduling permission: owner, administrator, and manager can manage closures; employee cannot. The organization switcher does not broaden access and only lists active memberships already available to the authenticated user.

M7-R6 adds one table through `2026_08_27_000050_create_organization_holidays_table.php`. It adds no Composer dependency, environment variable, queue, or scheduled command.
