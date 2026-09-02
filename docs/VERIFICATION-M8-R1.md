# M8-R1 verification

## Executed in the packaging workspace

- **53 JavaScript tests passed** with `node --test tests/JavaScript/*.test.cjs`.
- The ticket configuration tests execute the actual appointment-type form script against a DOM stub and verify mode forcing, option hiding/restoration and paid-only seating-fee fields.
- All 375 application, database, route, test, config and bootstrap PHP files passed a static PHP grammar parse.
- All three inline appointment-type form scripts passed JavaScript syntax parsing.
- `composer.json` passed JSON parsing, `git diff --check` was clean, and the source archive passed ZIP integrity verification.

## Laravel coverage added but not executed here

- Ticketed configuration accepts per-attendee pricing and normalizes each seating-block fee in organization currency.
- Single attendance, variable duration, fixed-total pricing and duration-rate pricing are rejected for ticketed events.
- Free events reject seating-block fees.
- Held allocations contribute the correct fees to booking totals, booking price lines and individual ticket snapshots across multiple buyers.

PHP, Composer dependencies, MariaDB and Memcached are unavailable in this workspace, so PHP lint, migrations, Blade compilation, Pint and the Laravel suite have **not** run here. Run the upgrade guide's complete verification on a dedicated test host before production deployment.
