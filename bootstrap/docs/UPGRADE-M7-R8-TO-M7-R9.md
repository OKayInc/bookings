# Upgrade M7-R8 to M7-R9

M7-R9 is an additive upgrade. It preserves existing users, organizations, memberships, resources, appointment types, schedules, holds, appointments, bookings, confirmations, calendars, holidays, and stored files.

1. Back up the MariaDB database, application `.env`, and runtime `storage`.
2. Apply `m7-r8-to-m7-r9.patch` to an M7-R8 source tree, or replace the source with the M7-R9 full package while preserving `.env` and runtime `storage`.
3. Install the existing locked dependencies and run the additive migration:

```bash
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan optimize:clear
```

Do not run `migrate:fresh`. M7-R9 has no new Composer package.

4. Run focused tests followed by the complete suite:

```bash
php artisan test --filter=ReplacementResourceTest
php artisan test --filter=ResourceRequirementTest
php artisan test --filter=StaffConfirmationTest
php artisan test --filter=RegionalHolidayAvailabilityTest
php artisan test --filter=ExternalCalendarAvailabilityTest
php artisan test
```

5. Verification scenario:

   - Edit an appointment type and select two person resources.
   - Set both requirement modes to **One of a replacement group** and enter the same group name, such as `Photographer`.
   - Make one candidate unavailable and confirm the slot remains bookable through the other candidate.
   - With both available, create a booking and confirm both candidates receive requests.
   - Decline from one candidate and confirm the booking remains pending.
   - Accept from the other candidate and confirm the booking becomes confirmed, the accepted resource remains assigned, and the other candidate displays **Not needed** and becomes available for another appointment.
