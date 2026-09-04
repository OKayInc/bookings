# M9-R5 verification

## Completed in the packaging workspace

- Existing JavaScript regression suite: **61 passed, 0 failed**.
- `node --check public/js/dashboard-filters.js`: passed.
- Source diff whitespace/error check: passed.
- Release ZIP integrity check: passed.
- Upgrade patch applied to a pristine copy of M9-R4 and compared byte-for-byte with the M9-R5 source package: passed.
- SHA-256 checksums produced for the release ZIP, upgrade patch, and standalone release instructions.

## Added host regression coverage

`tests/Feature/M9R5DashboardTest.php` covers authentication, active-organization isolation (including users who belong to multiple organizations), employee resource assignment, duplicate-resource handling, chronological ordering, cancelled/declined bookings, all date ranges, exclusive midnight boundaries, daylight-saving transitions, month-end clamping, overnight in-progress bookings, finished-event exclusion, page sizes and pagination, independent booking/payment badges, free bookings, empty results, and invalid query parameters.

`tests/Feature/BladeCompilationTest.php` now includes both dashboard templates.

## Not executed here

PHP, Composer, and MariaDB are unavailable in this workspace. Therefore PHP syntax checks, Blade compilation, Laravel feature tests, the full PHP regression suite, and live dashboard rendering have **not** been verified here. The JavaScript results do not establish that the PHP tests pass.

Run the focused and full PHP commands in `UPGRADE-M9-R4-TO-M9-R5.md` on the test host before deploying to production.
