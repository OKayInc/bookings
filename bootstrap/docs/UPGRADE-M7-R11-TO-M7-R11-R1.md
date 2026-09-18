# Upgrade M7-R11 to M7-R11-R1

M7-R11-R1 is a source-only Blade compatibility fix. It adds no migration and does not change stored short-notice fee rules or booking price snapshots.

1. Apply `m7-r11-to-m7-r11-r1.patch` to an M7-R11 source tree, or replace the source with the M7-R11-R1 full package while preserving `.env` and runtime `storage`.
2. Clear cached application and compiled-view files:

```bash
php artisan optimize:clear
php artisan view:clear
```

3. Run the two focused regression groups, then the complete suite:

```bash
php artisan test --filter=AppointmentTypeConfigurationTest
php artisan test --filter=ShortNoticeFeeTest
php artisan test
```

No `php artisan migrate` command is required for an installation already running M7-R11. If upgrading directly from M7-R10 or earlier, follow the M7-R11 upgrade guide and run its migration before applying this revision.
