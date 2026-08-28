# Upgrade M7-R4-R2 to M7-R4-R3

M7-R4-R3 adds one nullable preference column to `users` so organization switching is not dependent on session persistence.

```bash
php artisan optimize:clear
php artisan migrate
php artisan test --filter=CurrentOrganizationNavigationTest
php artisan test
```

Do not use `migrate:fresh` on an existing installation.

Existing users require no manual data update. Until they switch organizations, the resolver uses a valid existing session selection when available and otherwise falls back to their first active organization; that resolved organization is then persisted on organization-scoped requests.
