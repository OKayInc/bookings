# M7-R3-R1 verification

This revision fixes a test-fixture error only.

- `OrganizationLogoTest` no longer references `AppointmentType::factory()`.
- The replacement appointment type includes the same valid public-booking fields used elsewhere in the existing feature tests.
- PHP syntax validation was run against all PHP files in the packaged source.
- A clean M7-R3 -> M7-R3-R1 patch was generated and verified against the source trees.
- No production application files or migrations changed.

Run on the deployment/test host:

```bash
php artisan test --filter=OrganizationLogoTest
php artisan test
```
