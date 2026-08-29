# M7-R14 changes

M7-R14 simplifies public slot labels and allows appointments selected on one date to finish during available hours on a following date.

## Time-only slot boxes

- The public **Show available times** results now display only the local start/end time range, such as `6:00 PM – 2:00 AM`.
- The selected date remains visible in the date control and is no longer repeated in every result box.
- When the client timezone differs from the organization timezone, the secondary organization-time row is also time-only.
- Slot UTC timestamps and authoritative hold behavior are unchanged.

## Cross-midnight availability

- The requested date range now limits when an appointment may start; it is no longer treated as a deadline by which the appointment must finish.
- Schedule intersections, existing holds/appointments, holiday closures, required-resource calendars, and buffers are evaluated through the latest possible appointment end.
- A weekly availability rule ending at `23:59` is treated as ending at midnight because the HTML time input cannot represent `24:00`. It therefore joins a following-day rule beginning at `00:00` without an artificial one-minute gap.
- Real gaps remain enforced. For example, `18:00–23:45` followed by `00:00–03:00` does not permit an eight-hour appointment across midnight.
- Hold acquisition performs the same multi-day check again, so a stale or manually submitted start time cannot bypass next-day availability or conflicts.

M7-R14 has no database migration, new dependency, environment variable, queue, or scheduled-command change.
