# Upgrade M7-R4 to M7-R4-R1

This revision repairs migration `2026_08_27_000047_create_organization_resources_table.php` and is specifically safe for installations where the M7-R4 migration already failed with MariaDB error 1553 while dropping `cc_resource_provider_uq`.

Do not manually drop `organization_resources`, do not delete data, and do not run `migrate:fresh`.

1. Apply the M7-R4 -> M7-R4-R1 patch or replace the source with the M7-R4-R1 package.
2. Clear cached application state:

```bash
php artisan optimize:clear
```

3. Rerun migrations normally:

```bash
php artisan migrate
```

The repaired migration detects a partially created `organization_resources` table, adds a dedicated `calendar_connections(resource_id)` index for the existing foreign key, replaces the calendar uniqueness constraint, and reruns the resource backfill with `insertOrIgnore`.

4. Run targeted and full tests:

```bash
php artisan test --filter=SharedResourceTest
php artisan test
```
