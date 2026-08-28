# M2 Changes

M2 is the appointment-type configuration milestone built on M1 R4.

## Schema

Migration `2026_08_24_000009_expand_appointment_types_for_m2.php` adds:

- `attendance_mode`, `capacity`
- `duration_mode`, `duration_unit`, `duration_value`
- `minimum_duration_value`, `maximum_duration_value`, `duration_increment_value`
- `buffer_before_minutes`, `buffer_after_minutes`
- `pricing_mode`, `fixed_price_minor`, `rate_amount_minor`, `rate_unit`
- `requires_resource_confirmation`
- `redirect_url`

Existing M1 appointment types migrate safely as:

- single attendee,
- 60-minute fixed duration,
- no buffer,
- free,
- no resource confirmation.

Migration `2026_08_24_000010_create_appointment_type_invitations_table.php` creates UUID-based invitation records. Raw invite tokens are never stored; only SHA-256 token hashes are retained.

All explicitly named M2 indexes/foreign keys remain well below MariaDB's 64-character identifier limit.

## Access behavior

- Public: `/o/{organization}/a/{slug}` and visible on `/o/{organization}`.
- Password protected: same direct URL, but requires the configured password; successful access is kept in the browser session.
- Unlisted: `/o/{organization}/u/{random-token}`; omitted from the public list.
- Invite only: `/o/{organization}/i/{random-token}`; omitted from the public list. Invitations may be recipient-specific, expiring and/or usage-limited.

Opening an invite does **not** consume a use. M4 will increment the count only after a booking is successfully committed.

If an appointment type is changed away from invite-only, existing active invitations are revoked.

## Duration and pricing

Variable durations use one selected duration unit and have a minimum, maximum and increment. The validator requires the configured increment to land exactly on the maximum from the minimum.

Rate pricing can use a different unit. Example: a client may choose 30-minute increments while the service is priced per hour. M2 calculates rate totals using integer arithmetic and deterministic half-up rounding.

Money is stored in currency minor units. Common ISO 4217 zero-decimal and three-decimal currencies are handled; unknown/custom codes default to two decimal places.

## Files

Contract templates stay private/versioned exactly as in M1.

Appointment-specific logos are public presentation assets and are stored on Laravel's `public` disk. Run `php artisan storage:link` if the public storage link does not already exist.
