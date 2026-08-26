# M2 Fresh Installation

For an existing M1 R4 installation, use `docs/UPGRADE-M1-R4-TO-M2.md` instead.

## Requirements

- PHP 8.3 or newer
- MariaDB 10.11+ recommended
- Memcached server
- PHP extensions normally required by Laravel plus `pdo_mysql` and `memcached`
- Composer 2

## MariaDB database

Example:

```sql
CREATE DATABASE appointment CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'appointment'@'localhost' IDENTIFIED BY 'replace-this-password';
GRANT ALL PRIVILEGES ON appointment.* TO 'appointment'@'localhost';
FLUSH PRIVILEGES;
```

## Load MariaDB timezone information

```bash
mariadb-tzinfo-to-sql /usr/share/zoneinfo | mariadb -u root mysql
```

Verify:

```sql
SELECT CONVERT_TZ('2026-01-15 12:00:00', 'UTC', 'America/Toronto');
```

The result must not be `NULL`.

## Application setup

```bash
composer install
cp .env.example .env
php artisan key:generate
```

Configure MariaDB and Memcached in `.env`, then:

```bash
php artisan migrate
php artisan storage:link
php artisan app:timezone-health
php artisan test
```

Open `/register` to create the first person/account and organization. There are no default credentials.

## File storage

- Contract templates: private `local` disk (`storage/app/private/...`).
- Appointment logos: public disk (`storage/app/public/...`) exposed by `php artisan storage:link`.

Do not expose the private contract directory.
