# M5 verification

Static verification performed on the packaged source:

- all PHP source/tests/migrations pass `php -l`;
- `composer.json` parses as valid JSON;
- all explicit new MariaDB index/foreign-key names are below the 64-character identifier limit;
- M5 migrations use UUID/BINARY(16) entity IDs and short explicit index/constraint names;
- M4-R5 → M5 patch applies cleanly and reproduces the packaged tree exactly;
- route names introduced for questionnaire administration, quote preview and private answer-file downloads are present;
- no SQLite test dependency was introduced.

## Runtime verification required on installation

This build environment does not contain Composer/vendor packages, `pdo_mysql`, or Memcached, so the full Laravel suite cannot be executed here. On the target installation run:

```bash
composer install --no-interaction
php artisan optimize:clear
php artisan migrate
php artisan test
```

For address-question tests/production, configure Google Address Validation API as documented. Automated address tests fake the HTTP response and do not require a real Google request.
