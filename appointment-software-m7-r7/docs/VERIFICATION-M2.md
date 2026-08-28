# M2 Verification Notes

The generated M2 package received static verification in the generation environment:

- all PHP application/config/migration/test files pass `php -l` syntax checks;
- all JSON files parse successfully;
- M2 schema identifiers were reviewed against MariaDB's 64-character identifier limit;
- M2 uses additive migrations on top of M1 R4 rather than modifying the already-installed M1 migrations;
- no bigint/incrementing entity primary key was introduced; the new invitation entity uses UUIDv7/BINARY(16);
- raw invite-only tokens are not persisted; SHA-256 hashes are stored;
- appointment logo extensions exclude SVG to avoid serving unsanitized active SVG content;
- pricing amounts are stored/calculated as integer minor units;
- URL redirect validation accepts HTTP/HTTPS only;
- password access continues to use Laravel hashing and is throttled on the public unlock route;
- public listing continues to expose only appointment types with `visibility=public`.

## Added tests

- `AppointmentTypeConfigurationTest`
  - group capacity;
  - variable duration/increment persistence;
  - invalid increment rejection;
  - rate pricing persistence;
  - employee-resource confirmation prerequisite.
- `AppointmentTypeAccessTest`
  - unlisted link access;
  - password unlock/session behavior;
  - invite creation/open/revocation.
- `AppointmentTypePricingServiceTest`
  - minute duration priced by hour;
  - week duration priced by day.
- `MoneyServiceTest`
  - CAD minor units;
  - zero-decimal currency validation.
- model/table mapping regression suite now includes `AppointmentTypeInvitation`.

## Runtime limitation of the generation environment

The source-generation container has PHP but does not have Composer or the required database/cache PHP extensions. The full Laravel runtime test suite and MariaDB migrations therefore could not be executed here.

Run on the target development installation:

```bash
composer install
php artisan optimize:clear
php artisan migrate
php artisan storage:link
php artisan app:timezone-health
php artisan test
```
