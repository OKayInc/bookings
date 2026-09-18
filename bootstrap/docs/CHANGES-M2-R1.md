# M2 Revision 1

## Test database fix

M2 originally configured PHPUnit to use SQLite `:memory:`. This caused `could not find driver` on systems without PDO SQLite and, more importantly, did not match the project's MariaDB database requirement.

Changes:

- PHPUnit now uses a dedicated `mariadb_testing` Laravel connection backed by the MariaDB driver.
- Test database settings use `TEST_DB_*` variables; the default test database is `appointment_testing`.
- Added `.env.testing.example`.
- Added `docs/TESTING.md`.
- Added a safety guard in `tests/TestCase.php` that refuses destructive tests against a non-test database.
- Cache/session/mail/queue test drivers remain isolated (`array` / `sync`).
