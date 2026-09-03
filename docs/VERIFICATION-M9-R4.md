# M9-R4 verification

## Automated coverage

- configuration stores trigger/default answer identity, one-of-N mode and optional resource membership;
- a trigger answer promotes every available one-of-N candidate under the configured replacement group;
- an unavailable group hides the question and forces the server-owned default even when a trigger answer is forged;
- all mode is unavailable unless every configured resource can be held;
- existing JavaScript questionnaire, numeric constraint, equipment and ticket configuration coverage remains green.

## Workspace verification

- JavaScript tests: 61 passed, 0 failed.
- `git diff --check`: clean.

The packaging workspace does not provide PHP, Composer, MariaDB or Memcached. Run the host verification below before deployment.

## Required host verification

```bash
php artisan optimize:clear
php artisan migrate --pretend
php artisan migrate
php artisan test
./vendor/bin/pint --test
```
