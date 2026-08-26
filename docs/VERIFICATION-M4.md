# M4 verification report

The generated M4 source was statically checked in the build environment.

## Completed checks

- PHP syntax lint across application, migrations, routes, tests, config and Blade source files.
- JSON validation for `composer.json`.
- New MariaDB migration identifiers checked against the 64-character identifier limit.
- New migrations use UUID/BINARY(16) entity keys rather than incrementing bigint IDs.
- New private contract files are served through authorized Laravel routes rather than public storage paths.
- M3-R5 → M4 source diff generated.
- ZIP integrity checked after packaging.

## Added regression tests

- `GuestBookingFlowTest`
- `GroupBookingCapacityTest`
- `BookingEmailVerificationTest`
- `BookingContractReviewTest`
- expanded `ModelTableNameTest`

## Runtime limitation of this build environment

The build container has PHP but does not have Composer, `pdo_mysql`, or the PHP Memcached extension. Therefore the full Laravel application cannot boot against MariaDB here and `php artisan test` cannot be executed in this container.

Run on the target installation:

```bash
php artisan optimize:clear
php artisan migrate
php artisan test
```

The test suite remains protected by the existing dedicated-MariaDB-test-database guard.
