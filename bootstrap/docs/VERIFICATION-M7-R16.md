# M7-R16 verification

Verification prepared for this release includes:

- static whitespace/error checks with `git diff --check`;
- JavaScript syntax checking for the distance editor after Blade placeholders are substituted;
- unit coverage proving range minimums remain inclusive and maximums exclusive;
- unit coverage proving uncovered kilometer and mile distances use rounded-up fallback increments;
- unit coverage proving uncovered legacy configurations without a valid fallback fail closed;
- configuration coverage for required positive fallback values, JSON persistence, reusable-template copying, and editor rendering;
- end-to-end coverage proving an uncovered Google route uses the fallback in both the live quote and immutable booking price line;
- regression review for fixed fees, explicit zero-dollar ranges, private-origin secrecy, M7-R15 hidden questions, and short-notice percentage ordering;
- release patch application against a pristine M7-R15 source tree and byte-for-byte comparison with the M7-R16 full package during packaging;
- release ZIP integrity and SHA-256 manifest verification during packaging.

This packaging environment does not contain PHP, Composer dependencies, MariaDB, or Memcached, so PHP syntax lint and the Laravel test suite cannot run here. Run the commands in `UPGRADE-M7-R15-TO-M7-R16.md` on the deployment/test host before production rollout.
