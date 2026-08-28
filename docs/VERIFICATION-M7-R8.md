# M7-R8 verification

Verification completed in the packaging environment:

- `git diff --check` passes.
- A cross-organization feature test covers a person who retains an owner membership in organization 2 while accepting an employee membership in organization 1.
- Before acceptance, the invitee email is shown as pending but the unrelated person UUID is absent from selectable options.
- Direct preselection rejects a non-member with `404`.
- After acceptance, the backend account email appears in the current organization's resource picker and the direct member action preselects the person.
- Resource creation stores both the current organization and accepted member person identifiers.
- Existing role and tenant-isolation checks remain in the controller write path.
- The M7-R7-to-M7-R8 patch applies cleanly to a pristine M7-R7 source tree and is compared byte-for-byte with the M7-R8 full package during packaging.
- Release ZIP integrity and recorded SHA-256 checksums are checked during packaging.

This packaging environment does not contain PHP, Composer dependencies, MariaDB, or Memcached, so the Laravel test suite cannot run here. Run the commands in `UPGRADE-M7-R7-TO-M7-R8.md` on the deployment/test host before production rollout.
