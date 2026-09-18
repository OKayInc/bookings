# M9-R2 verification

## Automated coverage added

- a checkbox condition persists multiple acceptable option UUIDs;
- each configured answer independently makes the dependent question visible;
- unrelated selected answers keep the dependent question hidden;
- selecting an unrelated answer together with one configured answer shows the question;
- radio/select conditions reject multiple acceptable answers;
- client evaluation preserves existing AND/OR grouping;
- legacy single-answer client payloads remain supported.

## Workspace verification

- JavaScript tests: 61 passed, 0 failed.
- `git diff --check`: clean.
- The new public visibility helper passes `node --check`.

The packaging workspace does not provide PHP, Composer, MariaDB, or Memcached. Run the host verification below before deployment.

## Required host verification

```bash
php artisan optimize:clear
php artisan migrate --pretend
php artisan migrate
php artisan test
./vendor/bin/pint --test
```

In the question editor, create checkbox question A with three answers. Create question B, select A as its display dependency, select two acceptable answers, and confirm either answer shows B while the third answer alone does not.
