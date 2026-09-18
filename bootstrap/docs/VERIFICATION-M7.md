# M7 verification

The generated source contains 319 PHP files and is checked for:

- PHP syntax across application, migrations, routes, and tests.
- MariaDB explicit identifier names <= 64 characters.
- duplicate explicit foreign-key names across migrations.
- no auto-increment entity IDs in M7 migrations.
- M6-R2-R3 -> M7 patch reproducibility.
- ZIP integrity.

The build container does not include Composer/vendor dependencies or the MariaDB/Memcached PHP extensions, so the Laravel test suite must be executed on the target development/test installation:

```bash
php artisan test
```

Recommended focused tests:

```bash
php artisan test --filter=CalendarProviderTest
php artisan test --filter=CalendarConfigurationTest
php artisan test --filter=ExternalCalendarAvailabilityTest
php artisan test --filter=CalendarSyncTest
php artisan test --filter=CalendarBladeCompilationTest
```
