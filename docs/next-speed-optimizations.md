# Gallery, customer history and ticket-slot performance

Patch base: `fc6f09f9136d38ca95ec9223074a0d77ce4a5858` (`OKayInc/bookings`, main).

## Changes

- Gallery grids use a generated WebP preview fitted inside 640 × 640 pixels. The enlarged view and original-image links retain the existing full-size image. Small WebP files are reused; if resizing makes a WebP larger in bytes, the original is reused instead. Both the public grid and gallery editor use the preview. URLs come from stored metadata, with no file-existence checks or conversion during page rendering.
- New uploads and legacy image normalization generate previews. Existing photos display their original until the backfill command runs. Preview paths are unique to each generation, so browser caches can retain old URLs without confusing a replacement with an earlier image. Failed inserts clean up new files. Photo, appointment-type and organization deletion, including the purge workflow, remove previews along with originals.
- Preview metadata includes its byte size for inspection. Previews are system-generated derivatives and do not increase the customer's billed storage usage; the server needs additional disk space for them.
- Customer history loads 25 bookings per page, with Bootstrap pagination. Lifetime counts and net paid revenue are calculated in SQL over all of that customer's bookings, including refunds and cancelled/declined bookings as before. Refunds greater than payments contribute zero. The audit trail fetches only the latest 25 entries, and resolved access-list entries are no longer loaded for the active-status display.
- The public ticket-slot endpoint fetches active event occurrences in one batch and reuses the validated event attributes when formatting the response. For N returned slots, this replaces the previous 2N event-detail lookups with one. Other availability/inventory queries still run. Booking submissions retain a fresh occurrence lookup and all existing capacity and reservation checks.

No additional Redis settings, queue workers or crontab entries are required.

## Deployment

The patch adds four nullable columns to `gallery_photos`. Run the migration before reopening the site to uploads. Dependencies are unchanged. Use the website's Unix account and your normal release procedure. For a direct checkout, copy the patch to `/tmp/appointment-to-next-speed-optimizations.patch`, then run:

```bash
cd /var/www/appointment.to/web
git apply --check /tmp/appointment-to-next-speed-optimizations.patch
/usr/bin/php84 artisan down
git apply /tmp/appointment-to-next-speed-optimizations.patch
/usr/bin/php84 "$(command -v composer)" dump-autoload --optimize
/usr/bin/php84 artisan migrate --force
/usr/bin/php84 artisan optimize
/usr/bin/php84 artisan queue:restart
/usr/bin/php84 artisan up
```

Stop and resolve any error before proceeding to the next command. If web PHP has OPcache timestamp validation disabled, perform your usual deployment reload before reopening the site.

Generate previews for existing photos once, after deployment. The site can remain open while this runs:

```bash
cd /var/www/appointment.to/web
/usr/bin/php84 -d memory_limit=512M artisan gallery:generate-thumbnails
```

The command processes records in batches of 100. It skips ready previews, repairs missing preview files, reports failures and exits nonzero if any photo fails. It is safe to rerun after correcting a missing source file or storage error. It uses the existing GD/Imagick WebP support. Originals remain available throughout. After changing `gallery.thumbnail_size`, use `--force` to regenerate previews and remove their replaced files.

## Validation

The focused regressions cover:

- A detailed 1600 × 1000 fixture producing a smaller 640 × 400 preview; a graphic whose resized representation can be larger; small originals; public/admin/CDN URLs; original-file retention and unchanged billed storage usage.
- Backfill idempotency, missing-preview repair, forced regeneration, normalization, failed-insert cleanup and photo/type/organization/purge deletion.
- A 53-booking history across three pages, hydrating 25/25/3 booking models while retaining identical lifetime totals, tenant isolation, an empty history and bounded audit-trail reads.
- A 12-slot HTTP response using one event-occurrence query, with prices and show labels preserved; isolation between appointment types; changed/inactive occurrences despite stale loaded relations; invalid show timing and event modes.

Verified: **710 tests passed, one optional real-Redis test skipped, 4,257 assertions**. The focused group passed 66 tests. PHP syntax checks passed for all 15 changed/new PHP files, and Laravel configuration, event, route and view caches built successfully.

Runtime verification uses PHP 8.4.27, MariaDB 10.11.14 and the repository's locked dependencies (Laravel 13.40.0 and PHPUnit 12.5.43). PHPUnit is capped at 512 MB. Production response times and real-photo bandwidth savings must be measured on the deployed site; fixture operation counts are not a production speedup percentage.

## Rollback

Revert the code through the normal release process, regenerate the optimized autoloader and Laravel caches, and restart workers. Leave the nullable thumbnail columns in place initially: older code ignores them, and original images remain usable. The migration's `down()` removes the metadata columns but does not delete preview files; dropping them also removes the information used for preview cleanup, so do not drop them as part of a routine code rollback.
