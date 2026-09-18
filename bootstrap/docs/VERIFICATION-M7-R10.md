# M7-R10 verification

Verification performed for this release includes:

- static whitespace/error checks with `git diff --check`;
- model and route reference review for both reusable-question tables and the attachment endpoint;
- feature coverage proving that creating a question creates and links a reusable organization template with complete options and pricing;
- feature coverage proving that the create page lists only the active organization's questions and identifies already-attached questions;
- feature coverage proving complete attachment copying and duplicate-attachment suppression;
- feature coverage proving attachments remain independent, explicit template updates affect future attachments only, and cross-organization attachment returns 404;
- existing questionnaire deletion coverage preserving historical answers through disable-instead-of-delete;
- patch application against a pristine M7-R9 source tree and byte-for-byte comparison with the M7-R10 full package during packaging;
- release ZIP integrity and SHA-256 manifest verification during packaging.

This packaging environment does not contain PHP, Composer dependencies, MariaDB, or Memcached, so PHP syntax lint and the Laravel test suite cannot run here. Run the commands in `UPGRADE-M7-R9-TO-M7-R10.md` on the deployment/test host before production rollout.
