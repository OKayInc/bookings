# Upgrade M7-R12 to M7-R13

M7-R13 is a source-only organization-lifecycle upgrade. It does not automatically delete any existing data; deletion occurs only when an active owner completes the guarded danger-zone form.

1. Back up the MariaDB database, application `.env`, and the complete runtime `storage` directory. Organization deletion is permanent and restoration requires a backup.
2. Apply `m7-r12-to-m7-r13.patch` to an M7-R12 source tree, or replace the source with the M7-R13 full package while preserving `.env` and runtime `storage`.
3. Install the existing locked dependencies and clear cached routes, configuration, and views:

```bash
composer install --no-dev --optimize-autoloader
php artisan optimize:clear
```

No migration command is required when upgrading from M7-R12. Do not run `migrate:fresh`. M7-R13 adds no table, column, index, or Composer dependency.

4. Run focused tests followed by the full suite:

```bash
php artisan test --filter=OrganizationDeletionTest
php artisan test --filter=SharedResourceTest
php artisan test --filter=CurrentOrganizationNavigationTest
php artisan test
```

5. Verification scenario on a non-production organization:

   - Confirm an owner sees the danger zone while an administrator, manager, or employee does not.
   - Confirm an incorrect organization name or account password preserves the organization.
   - Share one resource into the test organization and one owned resource out to a surviving organization.
   - Delete the test organization and confirm the incoming resource remains with its owner, while the outgoing resource and its surviving-organization assignment/schedule/calendar setup are removed.
   - Confirm the deleted organization's bookings, contacts, files, calendar credentials, members, and invitations are gone.
   - Confirm former members' person/user accounts and their other organization memberships remain.
   - Confirm the user is switched to another active organization, or is sent to organization creation when none remains.
