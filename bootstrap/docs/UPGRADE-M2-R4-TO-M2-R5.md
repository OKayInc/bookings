# Upgrade M2-R4 to M2-R5

1. Back up the application and MariaDB database.
2. Apply the R4 -> R5 patch or replace the source tree with M2-R5 while preserving `.env` and writable storage.
3. Run:

```bash
composer install --no-interaction
php artisan optimize:clear
php artisan migrate
php artisan test
```

Two additive migrations are introduced:

- `2026_08_24_000011_create_organization_contacts_table.php`
- `2026_08_24_000012_add_email_verification_mode_to_appointment_types.php`

Existing appointment types receive `before_confirmation` automatically.

No existing user, person, organization, resource, appointment type, invitation, or contract data is removed.
