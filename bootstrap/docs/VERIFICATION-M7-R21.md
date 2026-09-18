# M7-R21 verification

## Executed in the packaging workspace

- **51 JavaScript tests passed** using `node --test tests/JavaScript/*.test.cjs`.
- Coverage includes all attendee comparison operators, mixed AND/OR operands, missing/invalid counts, using the rendered held count rather than question answers, hidden target cleanup, and editor transitions that disable stale operand fields.
- Editor and checkout event tests execute the actual view scripts against DOM stubs. They do not constitute rendered-browser or accessibility verification.
- Inline JavaScript syntax checks passed for the questionnaire form, numeric-constraint editor, and checkout, with Blade JSON expressions substituted by literals. The public numeric-constraint script also passed `node --check`.
- Static whitespace/diff checks, archive integrity, and checksum verification passed during packaging. The M7-R20 upgrade patch was applied to its exact baseline and compared byte-for-byte with the M7-R21 ZIP.

## Laravel coverage added (not executed here)

- `NumericQuestionConstraintTest`: save/edit/switch operand types, reject conflicting inputs, every operator and alias against attendee count, first-question and single-attendee use, mixed AND/OR operands, invalid/missing context, and legacy NULL-discriminator compatibility.
- `QuestionnaireBookingFlowTest`: two clients share a session but validate against their own held seat counts; forged request/answer counts cannot override the hold; invalid submissions create no booking or answer records.
- `BladeCompilationTest`: compile and PHP-lint the numeric constraint editor and public questionnaire/checkout views on a PHP-equipped host.

PHP, Composer dependencies, MariaDB, and Memcached are unavailable in this workspace, so PHP lint, Blade compilation, migrations, and the Laravel suite have **not** run here. Chromium is unavailable, so no rendered-browser verification is claimed. Run the upgrade guide's checks before production deployment.
