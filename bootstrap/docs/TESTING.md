# Testing Appointment Software with MariaDB

The feature test suite intentionally runs against MariaDB, not SQLite. The application uses MariaDB-specific behavior and the tests use Laravel `RefreshDatabase`, which creates and drops schema objects.

## 1. Create a dedicated test database

Create a database that is separate from your normal `appointment` database. For example:

```sql
CREATE DATABASE appointment_testing
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;
```

Grant your application database user access to that database using the account/host pattern appropriate for your installation. For example, if your application user is `appointment` and it connects remotely:

```sql
GRANT ALL PRIVILEGES ON appointment_testing.* TO 'appointment'@'%';
FLUSH PRIVILEGES;
```

Adjust the MariaDB account host (`%`, `localhost`, or a specific host) to match your existing user definition.

## 2. Configure the testing environment

Copy the provided example:

```bash
cp .env.testing.example .env.testing
```

Edit `.env.testing` so `TEST_DB_HOST`, `TEST_DB_PORT`, `TEST_DB_USERNAME`, and `TEST_DB_PASSWORD` match your MariaDB test server/account. Keep `TEST_DB_DATABASE` pointed at a dedicated test database such as `appointment_testing`.

Generate/set an application key if needed:

```bash
php artisan key:generate --env=testing
```

## 3. Run tests

```bash
php artisan optimize:clear
php artisan test
```

`phpunit.xml` selects a dedicated `mariadb_testing` Laravel connection. That connection uses `TEST_DB_*` variables and defaults to the database name `appointment_testing`; host and credentials fall back to the normal `DB_*` values if `TEST_DB_*` values are not supplied.

## Safety guard

`tests/TestCase.php` refuses to run the test suite unless:

- the selected connection uses the MariaDB driver; and
- the configured database name contains `test`.

This is deliberate because Laravel's `RefreshDatabase` trait is destructive to the configured test schema.

The test suite still uses in-memory/isolated substitutes for cache, sessions, mail and queues (`array`/`sync`) because those components do not need persistent infrastructure for these feature tests. Production remains configured for Memcached.

## Test encryption key

The PHPUnit configuration provides a fixed **test-only** Laravel `APP_KEY`:

```text
APP_KEY=base64:AQIDBAUGBwgJCgsMDQ4PEBESExQVFhcYGRobHB0eHyA=
```

This key exists only so Laravel encryption-dependent services can boot during automated tests. Do **not** use it as the production application key. Your normal installation must keep its own randomly generated key from `php artisan key:generate`.

If you maintain a local `.env.testing`, it may use the same test-only key. After changing test environment values, run:

```bash
php artisan optimize:clear
```

