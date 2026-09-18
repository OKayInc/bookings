# M7-R6 verification

Verification completed in the packaging environment:

- `git diff --check` passes.
- A delimiter-balance scan passes across all changed PHP files.
- The M7-R5-to-M7-R6 patch applies cleanly to the pristine M7-R5 full package and produces a source tree identical to the M7-R6 full package.
- The release ZIP integrity test and recorded SHA-256 checksums pass.
- The migration uses explicit MariaDB constraint/index names below the 64-character identifier limit.
- No holiday is seeded or enabled automatically.
- Fixed annual, Easter-relative, nth-weekday, and one-time rules are represented without a PHP calendar-extension dependency.
- Active closures are calculated in the organization timezone and override extra-availability exceptions.
- Booking-hold acquisition reuses authoritative availability and cannot bypass a holiday closure.
- Existing group sessions are not reintroduced on a closed date, and booking/reschedule hold consumption rechecks closures enabled after the hold was created.
- Holiday management follows existing scheduling permissions and tenant ownership checks.
- The navbar switcher lists only active memberships and uses the existing durable organization-switch route.
- Added focused feature coverage in `OrganizationHolidayAvailabilityTest`, `CurrentOrganizationNavigationTest`, and `BootstrapResponsiveUiTest`.

This packaging environment does not contain PHP, Composer dependencies, MariaDB, or Memcached, so PHP syntax lint and the Laravel test suite could not run here. Run the commands in `UPGRADE-M7-R5-TO-M7-R6.md` on the deployment/test host before production rollout.
