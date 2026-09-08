# M9-R8 verification

## Completed in the packaging workspace

- All 69 JavaScript tests passed, including the new gallery lightbox navigation test.
- `node --check public/js/gallery.js` passed.
- `git diff --check` passed.
- Release manifest and archive checks are completed during packaging.

## Host regression coverage added

`M9R8GalleryTest` covers immediate WebP conversion, hashed `.webp` storage, free-tier enforcement, free/paid cap resolution, organization and appointment ownership, tenant isolation, above/below public placement, CDN URL generation, and scheduled legacy-file normalization with original deletion.

`BladeCompilationTest` includes both gallery partials, and `ModelTableNameTest` includes `gallery_photos`.

## Not executable in this workspace

PHP, Composer, MariaDB, and the required image extensions are unavailable in the packaging container. PHP syntax, migration, Blade compilation, WebP encoder, and Laravel feature tests have not been executed here.

On staging, confirm that either `imagewebp()` is available or ImageMagick reports WebP support, then run the focused and complete commands from `UPGRADE-M9-R7-TO-M9-R8.md`. Also run `php artisan gallery:normalize-images --dry-run` and inspect both normal and CDN-generated public URLs before production deployment.
