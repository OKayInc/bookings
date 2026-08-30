# M7-R17 verification

Verification prepared for this release includes:

- organization-policy coverage proving owners can manage conference credentials while managers cannot;
- encrypted-at-rest assertions for organization Google API keys, provider secrets, refresh tokens, custom URLs, attendee URLs, and host URLs;
- secret-preservation coverage proving a blank replacement does not erase an existing secret;
- provider-catalog and appointment-type validation coverage proving Jitsi is always selectable and unconfigured remote providers are rejected;
- request-level tests for Google Meet OAuth/space creation, Teams client credentials/online meetings, Zoom Server-to-Server OAuth/meeting creation, and Webex refresh rotation/meeting creation;
- booking-flow coverage proving a new online Jitsi appointment receives a unique private join URL;
- fail-soft coverage proving provider errors are recorded without invalidating the booking transaction;
- review of public/backend link visibility, host-link isolation, and organization-deletion cascading;
- static whitespace/error checks with `git diff --check`;
- release patch application against pristine M7-R16-R1 and byte-for-byte comparison with the M7-R17 full package during packaging;
- release ZIP integrity and SHA-256 manifest verification during packaging.

This packaging environment does not contain PHP, Composer dependencies, MariaDB, or Memcached, so PHP syntax lint and the Laravel test suite cannot run here. Run the commands in `UPGRADE-M7-R16-R1-TO-M7-R17.md` on the deployment/test host before production rollout.
