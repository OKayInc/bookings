# M4-R3 verification

Static verification performed while packaging:

- PHP syntax lint across the source tree
- `phpunit.xml` XML parsing
- additive migration review
- no new long MariaDB index/constraint identifiers (the migration adds columns only)
- source scan confirming notice configuration is wired through model, request, controller, Blade form, public availability, and public hold acquisition
- regression tests added for calendar-month arithmetic, public slot filtering, hold bypass prevention, and appointment-type persistence

The build container does not contain the project's Composer dependencies or MariaDB test services, so run the complete suite after deployment:

```bash
php artisan optimize:clear
php artisan migrate
php artisan test
```
