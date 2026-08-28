# M7-R9 verification

Verification completed in the packaging environment:

- `git diff --check` passes.
- Feature coverage validates appointment-type group persistence and rejects a replacement group with fewer than two selected resources.
- Availability and hold coverage validates that one busy candidate does not remove a slot, all currently available candidates are snapshotted, and the selected hold rows retain the group name.
- Confirmation coverage validates decline-then-accept, first-acceptance supersession/release, and all-candidates-decline behavior.
- Regional holiday coverage validates that a Canadian holiday does not close a Canada/Mexico replacement group while the Mexican candidate remains open, and vice versa.
- Existing standalone required and optional resource tests remain in the focused upgrade test list.
- The M7-R8-to-M7-R9 patch is applied to a pristine M7-R8 source tree and compared byte-for-byte with the M7-R9 full package during packaging.
- Release ZIP integrity and recorded SHA-256 checksums are checked during packaging.

This packaging environment does not contain PHP, Composer dependencies, MariaDB, or Memcached, so PHP syntax lint and the Laravel test suite cannot run here. Run the commands in `UPGRADE-M7-R8-TO-M7-R9.md` on the deployment/test host before production rollout.
