# Upgrade M7-R7 to M7-R8

M7-R8 is a source-only additive upgrade. It preserves all users, persons, organizations, memberships, invitations, resources, schedules, appointments, bookings, calendars, holidays, and stored files.

1. Back up the MariaDB database, application `.env`, and runtime `storage`.
2. Apply `m7-r7-to-m7-r8.patch` to an M7-R7 source tree, or replace the source with the M7-R8 full package while preserving `.env` and runtime `storage`.
3. Clear compiled views and cached application state:

```bash
php artisan optimize:clear
```

M7-R8 has no migration and no new Composer package. Do not run `migrate:fresh`.

4. Run focused tests followed by the complete suite:

```bash
php artisan test --filter=OrganizationMemberInvitationTest
php artisan test --filter=RegionalHolidayAvailabilityTest
php artisan test --filter=BookingResourceNotificationTest
php artisan test
```

5. Verification scenario:

   - Keep person 1 as owner of organization 1 and person 2 as owner of organization 2.
   - From organization 1, invite person 2's backend account email as an employee.
   - Before acceptance, confirm the resource form shows the email as waiting and does not allow selection.
   - Sign in as person 2 and accept the invitation to organization 1.
   - Return as person 1, open **Organization → Members**, and use **Create person resource** for person 2.
   - Confirm person 2's account email is selected and that person 2 remains owner of organization 2.
