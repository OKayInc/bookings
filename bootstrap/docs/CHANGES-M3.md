# M3 changes

M3 adds the availability and concurrency foundation on top of M2-R5.

## Database

New appointment-type column:

- `appointment_types.start_interval_minutes` — defaults to 15.

New tables:

- `availability_schedules`
- `availability_rules`
- `availability_exceptions`
- `booking_holds`
- `booking_hold_resources`

All entity IDs continue to use UUIDv7 represented as MariaDB `BINARY(16)` values.

All newly introduced explicit index/constraint names are intentionally short enough for MariaDB's 64-character identifier limit.

## Availability hierarchy

- Organization schedule is the default inherited schedule.
- Resource schedules can override the organization schedule.
- Appointment-type schedules can override the organization schedule.
- The final availability window is the intersection of the appointment-type effective schedule and each assigned resource's effective schedule.
- Missing effective schedules mean unavailable.
- A disabled custom schedule means unavailable.

## Weekly rules

A schedule may have zero or more recurring intervals for each weekday. Multiple non-overlapping intervals on one day are supported.

Overnight intervals are deliberately represented as two rules split at midnight, which keeps DST and weekday behavior explicit.

## Exceptions

Each schedule supports:

- `unavailable` exceptions, which subtract availability;
- `available` exceptions, which add availability even outside recurring hours.

Exception input is entered in the schedule's IANA timezone and stored as UTC `DATETIME(6)` values plus the timezone snapshot.

## Slot generation

M3 adds a start-time interval separate from appointment duration. Example:

- duration: 60 minutes;
- start interval: 15 minutes;
- possible starts: 09:00, 09:15, 09:30, 09:45, etc.

Duration values in days/weeks are calculated as calendar units in the selected booking timezone instead of multiplying by a fixed number of seconds.

## Holds and concurrency

`BookingHoldService`:

1. opens a MariaDB transaction;
2. locks the appointment-type row with `lockForUpdate()`;
3. locks assigned resource rows in deterministic ID order;
4. rechecks availability;
5. creates the hold and resource links;
6. commits the transaction.

The raw hold token is returned once; only a SHA-256 hash is stored in MariaDB.

Expired/released/consumed holds do not block availability.

## Scheduler

A command was added:

```bash
php artisan appointments:expire-holds
```

It is registered in `routes/console.php` to run every minute via Laravel Scheduler.

## UI

New backend navigation entry: **Availability**.

Administrators/managers can:

- configure organization hours;
- configure/reset resource overrides;
- configure/reset appointment-type overrides;
- add/remove exceptions;
- preview generated appointment slots.

## Tests

M3 adds tests for:

- saving weekly availability;
- rejecting overlapping weekly intervals;
- resetting resource schedules back to inheritance;
- appointment/resource schedule intersection;
- blackout exception subtraction;
- temporary hold conflict rejection;
- timezone-aware day duration behavior across DST;
- variable-duration increment validation.
