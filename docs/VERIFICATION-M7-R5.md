# M7-R5 verification

Verification completed in the packaging environment:

- `git diff --check` passes.
- A delimiter-balance scan passes across all changed PHP files.
- The upgrade patch applies cleanly to the pristine M7-R4-R3 package and produces a source tree identical to the M7-R5 full package.
- The release ZIP integrity test and recorded SHA-256 checksums pass.
- The new migration uses explicit MariaDB constraint/index names below the 64-character identifier limit.
- Invitation routes are email-bound, token-hashed, throttled, expiring, revocable, and single-use.
- Owner invitations are rejected; only administrator, manager, and employee roles are accepted.
- New invitees reuse the existing M7 email-verification workflow.
- Existing users are required to authenticate as the invited email address and do not create duplicate person/user records.
- Booking-assignment delivery is invoked after the booking transaction commits.
- Resource recipients are grouped by normalized email to avoid duplicate messages.
- Resources that already received an immediate staff-confirmation request are excluded from the generic assignment email.
- Added focused feature coverage in `OrganizationMemberInvitationTest` and `BookingResourceNotificationTest`.

This packaging environment does not contain PHP, Composer dependencies, MariaDB, or Memcached, so PHP syntax lint and the Laravel test suite could not run here. Run the commands in `UPGRADE-M7-R4-R3-TO-M7-R5.md` on the deployment/test host before production rollout.
