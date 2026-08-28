# M7-R7 verification

Verification completed in the packaging environment:

- `git diff --check` passes.
- A delimiter-balance scan passes across changed PHP files.
- The M7-R6-to-M7-R7 patch applies cleanly to the pristine M7-R6 full package and produces a source tree identical to the M7-R7 full package.
- Release ZIP integrity and recorded SHA-256 checksums pass.
- Migration index names remain below MariaDB's 64-character identifier limit.
- Organization and resource regions are explicit saved values; timezone detection only supplies a suggestion/fallback and no GeoIP value silently changes scheduling.
- Exact regional selections are idempotent and date-equivalent configured holidays are suppressed/re-enabled rather than duplicated.
- Existing resources migrate with enforcement disabled.
- Required regional resource closures participate in slot generation and transactional hold acquisition; optional closed resources are skipped.
- Existing group sessions, final booking creation, and rescheduling recheck snapshotted required resources.
- A missing Yasumi installation now raises an actionable dependency error instead of silently producing an empty regional calendar.
- Resource create and edit routes pass the active organization and existing organization-resource holiday settings to the shared form partial.
- Focused feature coverage was added in `RegionalHolidayAvailabilityTest` and existing holiday/resource suites remain applicable.

This packaging environment does not contain PHP, Composer dependencies, MariaDB, or Memcached, so PHP syntax lint and the Laravel test suite could not run here. Run the commands in `UPGRADE-M7-R6-TO-M7-R7.md` on the deployment/test host before production rollout.
