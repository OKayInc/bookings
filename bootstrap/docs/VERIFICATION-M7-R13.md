# M7-R13 verification

Verification prepared for this release includes:

- static whitespace/error checks with `git diff --check`;
- route, controller, request-validation, policy, Blade danger-zone, and deletion-service review;
- feature coverage proving only active owners see and can use organization deletion;
- feature coverage for exact-name confirmation and Laravel's current-password validation;
- database coverage for bookings, appointments, contacts, appointment types, contract templates, and the organization row;
- storage coverage for organization logos, appointment-type logos, private contract templates, questionnaire uploads, and signed contract files;
- shared-resource coverage proving incoming resources survive and owned resources are unshared before deletion;
- orphan-schedule coverage for an owned resource configured by a surviving organization;
- active-organization coverage for selection of another membership and the no-membership organization-creation fallback;
- release patch application against a pristine M7-R12 source tree and byte-for-byte comparison with the M7-R13 full package during packaging;
- release ZIP integrity and SHA-256 manifest verification during packaging.

This packaging environment does not contain PHP, Composer dependencies, MariaDB, or Memcached, so PHP syntax lint and the Laravel test suite cannot run here. Run the commands in `UPGRADE-M7-R12-TO-M7-R13.md` on the deployment/test host before production rollout.
