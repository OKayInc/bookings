# M4-R4 verification

Static verification performed during packaging:

- PHP syntax lint passed for all PHP source/config/migration/test files in the package.
- No literal migration identifiers longer than MariaDB's 64-character identifier limit were found.
- The old `public_horizon_days` / `BOOKING_PUBLIC_HORIZON_DAYS` hard-coded horizon is no longer referenced by executable source/configuration.
- The M4-R3 → M4-R4 patch dry-runs cleanly with `patch -p1`.
- New regression tests cover maximum calendar-month notice, unlimited (`0`) maximum, public slot filtering, hold bypass prevention, and appointment-type persistence.

The full Laravel test suite was not executed in the packaging environment because the project dependencies/database extensions are not installed there. Run `php artisan test` against the configured MariaDB test database after upgrading.
