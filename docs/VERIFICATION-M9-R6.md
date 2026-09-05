# M9-R6 verification

## Completed in the packaging workspace

- JavaScript regression suite: **62 passed, 0 failed**.
- `node --check public/js/page-loader.js`: passed.
- JSON manifest parsing: passed.
- Source diff whitespace/error check: passed.
- Release ZIP integrity check: passed.
- Upgrade patch applied to a pristine M9-R5 tree and compared byte-for-byte with the M9-R6 source: passed.
- SHA-256 checksums produced for the release ZIP, upgrade patch, and standalone instructions.

## Added host regression coverage

`tests/Feature/M9R6ResourceDepositTest.php` covers equipment without a person, resource defaults, inherited and explicit-zero question overrides, per-piece quantities, immutable booking snapshots, deposits on top of retainers, coupon isolation, ordinary-refund reservation, partial-reason validation, full/partial original-capture refunds, and settled workflow behavior after return.

`tests/Feature/M9R4ConditionalResourceRequirementTest.php` now checks persistence of question-level deposit overrides and inherited null values. `tests/Feature/M9R6PageLoaderTest.php` checks the loader contract in both layouts, including the versioned asset and CDN preconnect. `tests/Feature/BladeCompilationTest.php` includes the loading partial and both layouts. `tests/JavaScript/page-loader.test.cjs` checks ready-state hiding, navigation display, form display, and browser-history restoration.

## Not executable in this workspace

PHP, Composer, and MariaDB are unavailable here. PHP syntax checks, migrations, Blade compilation, Laravel feature tests, the full PHP regression suite, and live provider calls have therefore not been executed. The JavaScript results do not establish that the PHP tests pass.

Run every focused and full host command in `UPGRADE-M9-R5-TO-M9-R6.md` against staging before production deployment.
