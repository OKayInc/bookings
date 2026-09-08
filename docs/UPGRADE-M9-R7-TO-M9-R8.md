# Upgrade M9-R7 to M9-R8

Back up MariaDB and the existing storage directory. Ensure PHP has either GD with WebP support or ImageMagick with WebP support before enabling uploads.

Deploy the M9-R8 source while preserving the live `.env`, installed dependencies, and runtime storage. Then add the desired gallery/CDN settings from `.env.example` and run:

```bash
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan storage:link
php artisan optimize:clear
php artisan optimize
```

All existing organizations receive `plan_tier=free`. Until M11 adds full plan administration, an operator may mark a paid organization explicitly, preferably by its unique slug:

```sql
UPDATE organizations SET plan_tier = 'paid' WHERE slug = 'organization-slug';
```

The four gallery cap variables apply independently:

```dotenv
GALLERY_FREE_MAX_ORGANIZATION_PHOTOS=6
GALLERY_PAID_MAX_ORGANIZATION_PHOTOS=60
GALLERY_FREE_MAX_APPOINTMENT_PHOTOS=6
GALLERY_PAID_MAX_APPOINTMENT_PHOTOS=30
```

For a CDN, configure its origin to serve the application's public files and set a complete HTTPS origin without `/storage`:

```dotenv
CDN_ENABLED=true
CDN_URL=https://images.appointment.to
```

After changing environment values, run `php artisan optimize:clear && php artisan optimize`. Confirm that the CDN accepts `/storage/...` paths, uses the application public-storage origin, and preserves WebP content types. The database continues to contain relative paths, so disabling the CDN is a configuration-only rollback.

The existing once-per-minute Laravel Scheduler cron is required. It now invokes `gallery:normalize-images` every Monday at 02:30. A manual audit is available:

```bash
php artisan gallery:normalize-images --dry-run
php artisan gallery:normalize-images
```

## Verification

```bash
php artisan test --filter=M9R8GalleryTest
php artisan test
node --test tests/JavaScript/*.test.cjs
```

Test uploads and deletions in both gallery editors, public above/below placement, enlargement on mobile and desktop, free/paid caps, CDN enable/disable, and deletion cleanup on staging.

Rolling the migration back drops gallery metadata and `organizations.plan_tier`; it deliberately does not recursively delete the physical gallery directory. Preserve or remove those files according to the recovery plan.
