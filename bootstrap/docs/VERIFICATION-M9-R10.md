# M9-R10 verification

## Checks completed in this workspace

- `git diff --check` passes.
- PHP 8.4.15 lint passes for every changed PHP source and test file.
- `php artisan view:cache --env=testing` compiles all Blade templates successfully.
- The availability analysis view, responsive timeline styles, request validation, controller integration, and release documentation were reviewed together.
- The focused availability-analysis suite passes: **4 tests, 23 assertions**.
- The JavaScript suite passes: **71 tests**.
- The complete Laravel suite passes against MariaDB 10.11.14: **424 tests, 2,465 assertions**.
- The source manifest, ZIP integrity test, and archive-to-manifest checksum verification pass during packaging.

## Regression coverage added

`AvailabilityPreviewAnalysisTest` verifies:

- required-resource conflict explanations and the authoritative available-start count;
- organization activity, appointment-type, and required-resource timeline rows;
- opt-in purple optional-resource rows that are absent by default;
- the explicit missing-effective-schedule explanation; and
- aggregate and member rows for one-of-N replacement groups.

Existing availability engine, replacement resource, regional holiday, external calendar, equipment quantity, shared resource, and cross-midnight tests remain applicable to the policies explained by the new view.

Production deployment should still follow the staging verification steps in `UPGRADE-M9-R9-TO-M9-R10.md` with the deployment environment's normal MariaDB configuration.
