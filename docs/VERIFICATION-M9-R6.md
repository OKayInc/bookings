# M9-R6 verification

## Packaging-workspace checks

- Page-loader JavaScript unit coverage verifies normal navigation, exclusions, accessible busy state, initial loading and page restoration.
- JavaScript regression suite: **66 passed, 0 failed**.
- `node --check public/js/page-loader.js`: passed.
- Source diff whitespace/error check: passed.
- M9-R5 upgrade patch applied to a pristine source tree and compared with the M9-R6 package: passed.
- Release ZIP integrity check: passed.
- Packaged source SHA-256 manifest verification: passed.

## Host regression coverage

`tests/Feature/M9R6PageLoaderTest.php` verifies that the backend and public layouts render the shared loader, versioned loader asset and CDN preconnection hint.

`tests/Feature/BladeCompilationTest.php` now compiles both shared layouts and the loader partial in addition to its previous templates.

`tests/Feature/M9R5DashboardTest.php` remains the regression coverage for the dashboard data whose count query was consolidated.

## Environment limitation

PHP, Composer and MariaDB are unavailable in the packaging workspace. PHP syntax checks, Blade compilation, Laravel feature tests and the full PHP regression suite must therefore be run on the test host before production deployment. JavaScript and packaging checks do not establish that the PHP suite passes.
