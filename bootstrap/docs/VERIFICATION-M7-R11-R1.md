# M7-R11-R1 verification

Verification prepared for this revision includes:

- review of the reported `AppointmentTypeConfigurationTest` stack trace and its Laravel compiled-view parse location;
- replacement of both new inline Blade PHP assignments with explicit `@php` / `@endphp` blocks;
- regression coverage that renders an appointment-type editor with no short-notice fee tiers;
- regression coverage that renders persisted fixed and percentage fee tiers with their original decimal values;
- static whitespace/error checks with `git diff --check`;
- patch application against a pristine M7-R11 source tree and byte-for-byte comparison with the M7-R11-R1 full package during packaging;
- release ZIP integrity and SHA-256 manifest verification during packaging.

This packaging environment does not contain PHP, Composer dependencies, MariaDB, or Memcached, so the Laravel test suite cannot run here. Run the commands in `UPGRADE-M7-R11-TO-M7-R11-R1.md` on the deployment/test host before production rollout.
