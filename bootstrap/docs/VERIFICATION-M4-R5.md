# M4-R5 Verification

Static verification completed in the build environment:

- PHP syntax lint passed for all project PHP files.
- The M4-R4 -> M4-R5 patch reproduces the packaged M4-R5 source tree exactly.
- No database migration was added.
- The appointment-type list loads both `resources_count` and `bookings_count`.
- The delete endpoint rechecks booking history in a MariaDB transaction before deleting.
- Appointment-type availability schedules are explicitly removed because their `scope_id` is intentionally not backed by a foreign key.
- Existing cascading foreign keys handle appointment-type resource pivots, invitations, holds, orphan appointment sessions and contract-template database rows.
- New regression coverage is in `tests/Feature/AppointmentTypeDeletionTest.php`.

The full Laravel test suite must be run in the configured MariaDB test environment:

```bash
php artisan optimize:clear
php artisan test
```

To run only the new feature tests:

```bash
php artisan test --filter=AppointmentTypeDeletionTest
```
