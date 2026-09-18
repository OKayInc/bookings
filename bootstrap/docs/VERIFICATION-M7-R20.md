# M7-R20 verification

## Executed in the packaging workspace

- **44 JavaScript tests passed** with `node --test tests/JavaScript/*.test.cjs`: shared decimal/operator fixtures, aliases, invalid inputs, AND/OR precedence, and checkout event wiring.
- Event-wiring tests execute the actual checkout script against a small DOM stub. They check Q1/Q2 changes, inline validity feedback, hidden source handling, and clearing a hidden target's error/value. They are not rendered-browser tests.
- Inline JavaScript syntax checks passed for the questionnaire editor, numeric-constraint editor, and public checkout, substituting literals for Blade JSON expressions during parsing.
- Static diff/whitespace checks, ZIP integrity, and SHA-256 manifest checks passed during packaging.
- The upgrade patch was applied to the exact M7-R19 baseline and compared byte-for-byte against the M7-R20 source ZIP.

## Laravel regression coverage added (not executed here)

- `NumericComparisonTest`: the same 38 numeric fixtures as JavaScript, all eight operator spellings, invalid/oversized input, and operator normalization.
- `NumericQuestionConstraintTest`: create/edit/remove, aliases, grouped AND/OR comparisons, fixed and question operands, optional/required/minimum/maximum behavior, hidden targets/sources, malformed rules, foreign/later/self/disabled/nonnumeric sources, transaction rollback, source lifecycle protection, reusable-copy isolation, cascades, and organization authorization.
- `QuestionnaireBookingFlowTest`: invalid quote rejection, direct booking rejection without JavaScript, configuration changed after a valid quote, no booking/answer persistence on failure, and successful booking after correction.

## Environment limitations

PHP, Composer dependencies, MariaDB, and Memcached are unavailable here. PHP syntax lint, Blade compilation, migrations, and the Laravel/PHPUnit suite have **not** run. Chromium is also unavailable, so no rendered-browser or mobile visual verification is claimed. Run the test commands and manual checks in the upgrade guide on a dedicated test installation before production deployment.
