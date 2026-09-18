# M7-R16-R1 verification

Verification prepared for this revision includes:

- review of the reported strict type mismatch between persisted integer `5` and expected float `5.0`;
- explicit float normalization at the JSON persistence boundary before the strict assertion;
- preservation of the exact expected numeric value and all production distance-fee behavior;
- static whitespace/error checks with `git diff --check`;
- patch application against a pristine M7-R16 source tree and byte-for-byte comparison with the M7-R16-R1 full package during packaging;
- release ZIP integrity and SHA-256 manifest verification during packaging.

This packaging environment does not contain PHP, Composer dependencies, MariaDB, or Memcached, so the Laravel test suite cannot run here. Run the commands in `UPGRADE-M7-R16-TO-M7-R16-R1.md` on the deployment/test host before production rollout.
