# Upgrade M4-R4 to M4-R5

M4-R5 adds appointment-type Delete/Disable lifecycle actions. It has no schema migration.

1. Back up the application source and database.
2. Apply `m4-r4-to-m4-r5.patch` from the application root, or replace the source with the M4-R5 package while preserving `.env` and runtime storage.
3. Clear Laravel caches:

```bash
php artisan optimize:clear
```

4. Run tests:

```bash
php artisan test
```

No `php artisan migrate` is required for this revision, though running it is harmless because there are no new migrations.
