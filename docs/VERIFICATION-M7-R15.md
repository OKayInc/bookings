# M7-R15 verification

Verification prepared for this release includes:

- static whitespace/error checks with `git diff --check`;
- configuration coverage for mixed AND/OR expressions and persisted condition ordering;
- evaluation coverage for `(Q1=A AND Q2=B) OR Q1=C` truth cases;
- server coverage proving a hidden required question is ignored rather than rejected;
- pricing and submission coverage proving forged hidden values create neither fees nor persistence payloads;
- coverage proving a visible required dependent question remains required;
- tenant/order validation proving sources must belong to the same appointment type and appear earlier;
- option-identity coverage proving edits preserve a referenced UUID and removal is blocked;
- review of client behavior for immediate chained show/hide, disabled fields, stale-answer clearing, required restoration, and live quote refresh;
- review that reusable templates do not copy attachment-specific dependencies;
- release patch application against a pristine M7-R14 source tree and byte-for-byte comparison with the M7-R15 full package during packaging;
- release ZIP integrity and SHA-256 manifest verification during packaging.

This packaging environment does not contain PHP, Composer dependencies, MariaDB, or Memcached, so PHP syntax lint and the Laravel test suite cannot run here. Run the commands in `UPGRADE-M7-R14-TO-M7-R15.md` on the deployment/test host before production rollout.
