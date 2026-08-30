# M7-R19 verification

## Regression coverage added

`AttendeePricingServiceTest` covers flat rates, absolute and accumulative boundary calculations, the $18/$23 examples, invalid counts, gaps, overlaps, incomplete capacity coverage, nonpositive prices, integer multiplication/addition overflow, and unchanged free/fixed/duration-rate behavior.

`PerAttendeePricingTest` covers owner configuration and editing, rejection for single attendance, clearing unused rules, invalid range rejection, consistent slot/quote/final-booking totals in all three modes, authoritative held counts, checkout without questionnaire extras, saved historical prices, two separate clients sharing a session, over-capacity rejection, questionnaire/short-notice fee ordering, and rejection of a mismatched base-price quote.

## Checks in the packaging workspace

- JavaScript syntax checks for the appointment-type editor, public scheduler, and checkout scripts (Blade JSON expressions replaced with test literals for syntax checking only).
- Static diff/whitespace checks.
- Upgrade patch application against the exact M7-R18 baseline, with byte-for-byte comparison of the patched source to the M7-R19 ZIP.
- ZIP integrity and SHA-256 manifest verification.

## Checks not executable here

PHP, Composer dependencies, MariaDB, and Memcached are unavailable, so PHP lint, Blade compilation, and the Laravel test suite have not run here. A browser DOM smoke test was attempted but could not start because the Chromium executable is unavailable. JavaScript syntax checks are not a substitute for rendered-browser or Laravel tests. Run the commands and manual checks in the upgrade guide before production deployment.
