# M9-R9 verification

## Static checks completed in this workspace

- `git diff --check` passes.
- All 71 JavaScript tests pass, including the organization-tax form state test.
- Tree-sitter parsed all application, migration, route, and test PHP files without syntax errors.
- The tax arithmetic was independently exercised against exact integer calculations, including multi-rate inclusive allocation and non-taxable deposits.
- Release archive and checksum verification are completed during packaging.

## Regression coverage added

`OrganizationTaxTest` covers organization form controls, server validation, multiple taxes, case-insensitive duplicate rejection, exact 9.975% storage, disabling/clearing configuration, tax-exclusive live quotes and booking snapshots, tax-inclusive extraction, immutable tax history after organization edits, payment amount integration, and deposit exclusion.

`BladeCompilationTest` now includes both organization form entry points and the shared organization form partial in addition to the existing checkout and booking views.

Existing questionnaire, coupon, attendee, equipment, deposit, ticketing, payment, refund, and public-booking tests continue to cover the upstream/downstream pricing paths that M9-R9 connects.

## Host verification required

PHP, Composer, and MariaDB are unavailable in this workspace image. PHP syntax checks, migrations, Blade compilation, and Laravel tests must therefore be run on the normal test host using every command in `UPGRADE-M9-R8-TO-M9-R9.md` before production deployment.
