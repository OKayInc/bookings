# M6-R2 Verification

M6-R2 was statically verified against the packaged M6-R1 source before release.

## Checks completed

- PHP syntax lint passed for **247 PHP files** under `app`, `database`, `routes`, `tests`, `config`, `bootstrap`, and `public`.
- `composer.json` parses as valid JSON.
- `phpunit.xml` parses as valid XML.
- New migrations are additive: `000040` and `000041`.
- New migrations use UUID/BINARY identifiers and introduce no incrementing entity IDs.
- All explicit MariaDB foreign-key names are unique across the migration set.
- No explicit migration index/constraint identifier exceeds MariaDB's 64-character limit.
- Literal Blade `route()` references resolve to declared routes/resource routes.
- `BookingScheduleProposal` is included in the model/table mapping regression test.
- No explicit skipped-test markers are present in the test source.
- The staff schedule proposal regression suite covers accept, required-confirmation reset, keep-original warning, cancellation origin, expiration, group-session hold lifetime, and the client three-choice UI.
- M6-R1 → M6-R2 changed-file entries before this verification document: **34**.

## Runtime test limitation in the build environment

The packaging environment does not provide Composer, `pdo_mysql`, or the PHP Memcached extension, so the Laravel/MariaDB test suite cannot be executed here. Run the full suite on the installation host after applying the upgrade:

```bash
php artisan optimize:clear
php artisan migrate
php artisan test
```

Do not use `migrate:fresh` on an existing installation.

## Scheduler

The existing once-per-minute Laravel Scheduler cron remains sufficient. M6-R2 adds the scheduled command:

```bash
php artisan appointments:expire-schedule-proposals
```

Laravel invokes it automatically every ten minutes through `routes/console.php`.

## M6-R2 Revision 1 note

The booking-management proposal block was simplified after a Blade compiled-view parse error was reported. Proposal selection now occurs in the controller, and the Blade block uses explicit multi-line directives.
