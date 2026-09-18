# M7-R18 verification

Verification prepared for this release includes:

- appointment-type request coverage for valid yearly configuration, reverse date rejection, and the less-than-one-year invariant;
- organization-timezone evaluation of yearly seasons and seasons crossing New Year;
- one-time non-recurrence and February 29 clamping coverage;
- inclusive final-day boundary coverage proving an appointment may end at the closing midnight but cannot extend beyond it;
- public catalog coverage proving an off-season appointment type is hidden while its direct detail URL remains available for future in-season booking;
- slot coverage proving out-of-season dates return no availability;
- existing group-session coverage proving a session is no longer joinable after its appointment type moves out of season;
- stale-hold coverage proving a season change before checkout blocks final booking and preserves the unconsumed hold;
- server-side review of new-session, existing group-capacity, hold, booking, rescheduling, and staff-proposal paths;
- Blade appointment-type JavaScript syntax checking;
- static whitespace/error checks with `git diff --check`;
- patch application against pristine M7-R17 and byte-for-byte comparison with the M7-R18 ZIP during packaging;
- release ZIP integrity and SHA-256 manifest verification during packaging.

This packaging environment does not contain PHP, Composer dependencies, MariaDB, or Memcached, so PHP syntax lint and the Laravel test suite cannot run here. Run the commands in `UPGRADE-M7-R17-TO-M7-R18.md` on the deployment/test host before production rollout.
