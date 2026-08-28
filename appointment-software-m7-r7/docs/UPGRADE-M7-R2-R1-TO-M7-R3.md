# Upgrade M7-R2-R1 to M7-R3

No database migration is required because `organizations.logo_path` already exists.

1. Apply the source patch or replace the source with the M7-R3 package.
2. Preserve `.env` and runtime `storage`.
3. If public storage has not been exposed yet, run:

```bash
php artisan storage:link
```

4. Optional `.env` setting:

```dotenv
ORGANIZATION_LOGO_DISK=public
```

5. Clear cached configuration/views and test:

```bash
php artisan optimize:clear
php artisan test --filter=OrganizationLogoTest
php artisan test
```
