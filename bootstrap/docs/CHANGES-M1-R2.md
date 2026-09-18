# M1 Revision 2 — Contract foundation

This revision supersedes the original M1 package for new installations.

## Added

- Optional contract template per appointment type.
- Private contract-file storage configuration.
- Contract upload on appointment-type create/edit forms.
- Authenticated staff download of the current contract template.
- Replace/remove controls for the active template.
- Version-preserving contract history with SHA-256 metadata.
- MariaDB uniqueness guard ensuring at most one active contract version per appointment type.
- `ContractReviewStatus` enum reserving `pending_review`, `approved`, and `rejected` for M4.
- Contract feature tests.
- Detailed future booking/signed-upload schema in `docs/contracts.md`.

## Deferred to M4 booking

- Customer-facing contract download after appointment access checks.
- Signed PDF upload.
- Multiple photographed/scanned page uploads.
- Manual staff review UI.
- Rejection notes and client resubmission.
- Snapshot of the exact contract template UUID/hash on each booking.

The customer-facing pieces are deferred because M1 does not yet contain appointments/bookings. Implementing them before the booking/access-control layer would either create orphan uploads or bypass invite/password rules.
