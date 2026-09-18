# M6 verification

The packaged M6 source was checked before release with the following static validations:

- PHP syntax lint across all PHP source files.
- Explicit MariaDB index / constraint identifiers checked against the 64-character identifier limit.
- Explicit foreign-key names checked for duplicates across all migrations.
- M5-R7 -> M6 patch dry-run and full reproduction check.
- ZIP integrity check.

The build environment does not provide the project's MariaDB/Memcached runtime and installed Composer vendor tree, so the full Laravel test suite must still be executed on the target installation after upgrade.

Recommended post-upgrade verification:

```bash
php artisan optimize:clear
php artisan migrate
php artisan appointments:sync-staff-confirmations
php artisan test
```

Do not use `php artisan migrate:fresh` on an existing installation.
