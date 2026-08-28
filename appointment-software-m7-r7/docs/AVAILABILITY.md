# Availability architecture

## Source of truth

MariaDB is authoritative for scheduling state. Memcached may later cache derived availability, but losing cache entries must never create or remove a reservation.

## Schedule scopes

`availability_schedules.scope_type` supports:

- `organization`
- `resource`
- `appointment_type`

`scope_id` stores the UUID/BINARY(16) identifier of the corresponding object. The combination of organization, scope type, and scope ID is unique.

The organization schedule uses the organization's own ID as `scope_id`.

## Inheritance

For an appointment type:

1. use its custom schedule if one exists;
2. otherwise use the organization schedule.

For a resource:

1. use its custom schedule if one exists;
2. otherwise use the organization schedule.

A custom schedule can be deleted/reset to resume inheritance.

## Weekly rules

`availability_rules` stores:

- weekday `0..6` (Sunday through Saturday);
- local `start_time`;
- local `end_time`;
- sort order.

Rules are interpreted in the parent schedule's IANA timezone and converted to UTC while generating availability.

## Exceptions

`availability_exceptions` stores absolute UTC periods.

Modes:

- `available` adds a window;
- `unavailable` subtracts a window.

The timezone used when creating the exception is also stored for audit/display purposes.

## Organization holiday closures

`organization_holidays` stores optional organization-wide closed dates. Nothing is imported or enabled automatically. An owner, administrator, or manager can add, disable, re-enable, or remove a closure from **Availability → Holiday closures**.

Supported date rules are:

- annual fixed month/day, such as Christmas Day;
- an offset from Gregorian Easter Sunday, such as Good Friday (`-2`);
- an nth weekday in a month, such as the second Monday in October;
- a one-time calendar date.
- an official/bank holiday supplied by the configured country or subdivision calendar.

The organization timezone suggests a country or subdivision, but the organization saves an explicit `holiday_region`. GeoIP is deliberately not authoritative because the administrator may be remote, travelling, behind a VPN, or managing a business in another country. The picker is backed by Yasumi providers and shows official/bank holidays for the selected region. The organization still decides which individual dates it observes; importing a provider does not enable every holiday.

Configured preset keys are unique per organization. The picker removes exact matches and also compares the next three resolved dates, preventing an older custom Christmas/Easter rule and its regional equivalent from being offered or stored as two effective closures.

Each active date is interpreted from local midnight to the following local midnight in the organization's IANA timezone, then converted to UTC. A holiday is a hard closure: it overrides organization, resource, and appointment-type hours as well as `available` exceptions.

## Resource holiday calendars

`organization_resources.enforce_holidays` optionally closes one resource on all official/bank holidays supplied for `organization_resources.holiday_region`. These settings belong to the organization-resource link, not the physical resource, so organizations sharing a resource can apply different jurisdictions.

The resource's own IANA timezone defines its holiday midnight boundary. Its timezone suggests a region when no explicit value exists, with the organization region/timezone as fallback. A required resource closure removes the appointment slot. An optional resource closure leaves the slot bookable but prevents that resource from being attached to the hold. With required Canadian and Mexican resources, availability therefore excludes the union of both regional calendars.

## Slot generation

For a requested UTC range:

1. build recurring windows from the effective appointment-type schedule;
2. apply its extra-availability and blackout exceptions;
3. build equivalent windows for each assigned resource;
4. intersect all schedule window sets;
5. align candidate starts to `start_interval_minutes` in the booking/display timezone;
6. calculate each candidate's real end time using the configured duration unit;
7. expand the candidate by `buffer_before_minutes` and `buffer_after_minutes` for conflict detection;
8. reject candidates that overlap an active organization holiday closure;
9. reject candidates that overlap a required resource's enforced regional holiday;
10. reject candidates that overlap active holds, appointments, or connected-calendar busy periods.

Booking-hold acquisition recalculates availability inside its transaction. Booking creation and rescheduling recheck both organization and snapshotted required-resource closures when consuming a hold. Existing group sessions are checked against their assigned resources. This ensures a stale browser result, crafted request, pre-existing group session, or hold created just before a closure was enabled cannot create or move a booking onto the closed date.

## Why start interval is separate from duration

A 60-minute service may reasonably begin every 15 minutes. Tying start times to duration would incorrectly restrict it to starts every 60 minutes.

## Timezones

Recurring schedule rules use named IANA zones such as `America/Toronto`.

Actual exception/hold instants use UTC `DATETIME(6)` values.

MariaDB timezone tables should remain populated using:

```bash
mariadb-tzinfo-to-sql /usr/share/zoneinfo | mariadb -u root mysql
```

Database reporting may therefore use `CONVERT_TZ()`. PHP/Carbon performs runtime scheduling calculations.

## Holds

`booking_holds` contains:

- appointment type;
- UTC appointment start/end;
- UTC blocked start/end including buffers;
- booking timezone;
- selected duration value;
- expiration time;
- status;
- SHA-256 token hash.

`booking_hold_resources` identifies all resources locked by the hold.

The raw token is never stored.

## Concurrency

Hold acquisition uses a MariaDB transaction and Laravel pessimistic locking. The appointment type is always locked; resource rows are locked in deterministic order. Availability is rechecked inside the same transaction before the hold is inserted.

This makes a stale browser availability result harmless: the second requester must recheck after the first transaction commits and receives an unavailable result if the first hold won.

## Current M3 limitation

M3 only knows about temporary holds because actual appointment/session booking rows arrive in M4. M4 must make confirmed/pending appointment resource reservations participate in the same conflict check before public booking is enabled.
