# Upgrade M9-R9 to M9-R10

Back up the application files, then deploy the M9-R10 source while preserving the live `.env`, installed dependencies, and runtime storage.

M9-R10 has no database migration. Run:

```bash
composer install --no-dev --optimize-autoloader
php artisan optimize:clear
php artisan optimize
```

Run focused and complete verification on the staging/test database:

```bash
php artisan test --filter=AvailabilityPreviewAnalysisTest
php artisan test --filter=AvailabilityEngineTest
php artisan test --filter=ReplacementResourceTest
php artisan test --filter=ExternalCalendarAvailabilityTest
php artisan test
```

Then open **Scheduling → Availability → Preview slots** and verify:

1. A type with a required resource shows a blue resource row and red conflict periods.
2. A scheduled appointment or live hold identifies its blocked local-time range.
3. A replacement group stays available while at least one member is free.
4. **Show optional resources in the analysis** adds purple rows without changing the result row.
5. On a narrow screen, the full-day timeline scrolls horizontally and focused segments expose their descriptions.
