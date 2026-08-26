# Upgrade M1 Revision 4 → M2

This upgrade preserves the existing M1 database and data.

## 1. Back up

Back up both the MariaDB database and the application `storage/` directory before replacing source files.

## 2. Replace/add source files

Either deploy the full M2 tree or apply the supplied `m1-r4-to-m2.patch` from the M1 R4 project root.

If applying the patch manually, review it first because local modifications may conflict.

## 3. Install/update dependencies

M2 adds no new Composer package requirement, but run the normal deployment command:

```bash
composer install --no-interaction
```

## 4. Clear cached framework state

```bash
php artisan optimize:clear
```

## 5. Run the two M2 migrations

```bash
php artisan migrate
```

Do **not** run `migrate:fresh` on an existing installation.

The existing appointment types receive safe defaults (single attendee, fixed 60 minutes, free, zero buffers).

## 6. Ensure the public storage link exists

M2 uses the public disk only for appointment logos:

```bash
php artisan storage:link
```

Contract files remain private and are not exposed by this link.

## 7. Verify

```bash
php artisan app:timezone-health
php artisan test
```

Then open the backend Appointment Types page, edit an existing type and save its new M2 configuration.

## Rollback

If necessary, code can be reverted and the two M2 migrations rolled back with:

```bash
php artisan migrate:rollback --step=2
```

Take care: rolling back M2 removes invitation data and the new appointment-type configuration columns.
