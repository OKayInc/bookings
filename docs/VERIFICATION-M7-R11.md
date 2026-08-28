# M7-R11 verification

Verification prepared for this release includes:

- static whitespace/error checks with `git diff --check`;
- reference review across appointment-type configuration, public hold quote, final booking pricing, and immutable price-line persistence;
- feature coverage for storing fixed and percentage rules in currency minor units and basis points;
- feature coverage for duplicate threshold rejection;
- deterministic pricing coverage for exact boundaries, non-stacking tier selection, fixed fees, percentage fees after questionnaire extras, and starts outside every tier;
- end-to-end booking-service coverage proving the matching fee is included in the booking total and snapshotted as a `short_notice_fee` price line;
- existing questionnaire-pricing compatibility through the optional appointment-start quote arguments;
- release patch application against a pristine M7-R10 source tree and byte-for-byte comparison with the M7-R11 full package during packaging;
- release ZIP integrity and SHA-256 manifest verification during packaging.

This packaging environment does not contain PHP, Composer dependencies, MariaDB, or Memcached, so PHP syntax lint and the Laravel test suite cannot run here. Run the commands in `UPGRADE-M7-R10-TO-M7-R11.md` on the deployment/test host before production rollout.
