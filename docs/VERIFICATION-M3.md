# M3 verification

The generated M3 package was checked in the build environment for:

- PHP syntax across application, migration, configuration, route, and test PHP files;
- MariaDB identifier-name length for explicit index/constraint names;
- absence of new bigint/incrementing entity primary keys;
- expected M3 migration set;
- ZIP archive integrity;
- generation of an M2-R5 → M3 patch.

The build environment does not provide Composer, the MariaDB PDO extension, or Memcached PHP extension, so the complete Laravel runtime test suite cannot be executed there.

Run these commands on the real installation:

```bash
php artisan optimize:clear
php artisan migrate
php artisan test
php artisan appointments:expire-holds
```

Then configure organization hours and inspect **Availability → Preview slots**.
