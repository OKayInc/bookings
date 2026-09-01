# M8 verification

## Automated coverage added

- Ticketed appointment-type configuration, including optional section/row seat components.
- Rejection of invalid timing and seating inventory definitions.
- Event timing snapshots and consecutive allocation across multiple buyers in one shared session.
- One-ticket-per-attendee issuance and reserved status for `pending_payment` bookings.
- Tenant-authorized, case-normalized, single-use check-in.
- Ticket voiding, allocation-key release and resale of cancelled seats.
- Ticket voiding and seat release when unverified bookings expire.
- Future-event timing and inventory configuration lock.
- Blade compilation coverage for ticket configuration, check-in and printable ticket views.

## Environment limitation

The packaging workspace does not provide PHP, Composer, MariaDB or Memcached. Laravel migrations, PHP lint, Blade compilation, Pint and the Laravel suite therefore must be executed on the deployment/test host with the commands in `UPGRADE-M7-R21-TO-M8.md`. JavaScript and repository-level static checks can be executed in this workspace and their final result is recorded in the implementation handoff.

## Workspace result

- `node --test tests/JavaScript/*.test.cjs`: 51 passed, 0 failed.
- Static PHP grammar parse: 372 application, migration, route and test files parsed with 0 failures.
- Appointment-type form scripts: 3 parsed with 0 failures.
- `composer.json`: valid JSON.
- `git diff --check`: clean.
