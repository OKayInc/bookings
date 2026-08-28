# Upgrade M7-R3-R1 to M7-R4

Apply the patch/source, preserve `.env` and runtime storage, then run:

```bash
php artisan optimize:clear
php artisan migrate
php artisan test --filter=SharedResourceTest
php artisan test
```

The migration creates `organization_resources` and backfills all existing resources to preserve current behavior. Do not run `migrate:fresh` on an active installation.
