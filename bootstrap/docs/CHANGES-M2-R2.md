# M2 Revision 2

## Fixes

- Fixes `MissingAppKeyException` when running `php artisan test`.
- Adds a deterministic **test-only** `APP_KEY` to `phpunit.xml`.
- Adds the same test-only key to `.env.testing.example`.
- The PHPUnit entry uses `force="true"` so the automated test suite does not inherit a missing/blank application key from the shell or local environment.
- Production/development `APP_KEY` configuration is unchanged.

## Upgrade

No database migration is required. Replace `phpunit.xml` (and optionally `.env.testing.example` / docs), then run:

```bash
php artisan optimize:clear
php artisan test
```
