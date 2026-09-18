# Upgrade M5-R6 to M5-R7

Back up the database and application files before upgrading.

Apply the patch or replace the source files while preserving `.env` and application storage, then run:

```bash
php artisan optimize:clear
php artisan migrate
php artisan test
```

Do not run `migrate:fresh` on an installation containing data.

M5-R7 adds one additive migration:

`2026_08_25_000034_add_resource_requirement_configuration.php`

The migration preserves current behavior:

- existing resources become Required by default;
- existing appointment-type resource assignments use `inherit`;
- existing hold and appointment resource snapshots are marked Required.

After upgrading, edit Resources to set organization defaults and edit Appointment Types to choose per-type overrides.
