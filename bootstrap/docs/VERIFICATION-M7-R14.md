# M7-R14 verification

Verification prepared for this release includes:

- static whitespace/error checks with `git diff --check`;
- feature coverage for time-only client and organization slot labels;
- availability-engine coverage for an eight-hour `18:00–02:00` appointment returned for its selected start date;
- hold-acquisition coverage proving the same cross-midnight slot remains valid during authoritative rechecking;
- regression coverage proving a real midnight gap still rejects the slot;
- regression coverage proving a following-day appointment conflict rejects the slot;
- review that schedule, resource, busy-period, holiday, buffer, and external-calendar queries extend through the appointment's possible end while candidate starts remain bounded by the requested date;
- release patch application against a pristine M7-R13 source tree and byte-for-byte comparison with the M7-R14 full package during packaging;
- release ZIP integrity and SHA-256 manifest verification during packaging.

This packaging environment does not contain PHP, Composer dependencies, MariaDB, or Memcached, so PHP syntax lint and the Laravel test suite cannot run here. Run the commands in `UPGRADE-M7-R13-TO-M7-R14.md` on the deployment/test host before production rollout.
