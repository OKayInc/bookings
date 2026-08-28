# Contract workflow

## M1: template foundation

Each appointment type may have zero or one **active** contract template. Contract files are private and are downloaded only through application controllers after authorization. Replacing or removing a contract never destroys its historical version; it marks that version inactive. This matters because a future booking must keep the exact contract version originally presented to the client.

Template metadata includes the original filename, MIME type, byte size and SHA-256 checksum. The SHA-256 value is an integrity/audit identifier, not an electronic signature.

## M4: client booking workflow

When booking an appointment type that has an active contract:

1. The booking flow snapshots the active `appointment_contract_template_id` and SHA-256.
2. After the appointment's normal access checks pass, the client can download that exact template.
3. The client uploads either one signed PDF or multiple photographs/scans of signed pages.
4. Files are stored privately under the configured contract disk.
5. A contract submission begins in `pending` state.
6. Authorized organization staff manually review it and mark it `approved` or `rejected`.
7. A rejection can include review notes and permits a new submission. Previous attempts remain in history.

This is a manual document-review workflow; it is not an electronic-signature service and does not attempt to cryptographically prove who signed the document.

## Planned booking tables

### `booking_contract_submissions`

- `id BINARY(16)` UUIDv7 primary key
- `organization_id BINARY(16)` tenant boundary
- `booking_id BINARY(16)` booking being reviewed
- `contract_template_id BINARY(16)` exact template version shown to the client
- `attempt_number` integer beginning at 1
- `status`: `pending`, `approved`, `rejected`
- `submitted_at`
- `reviewed_by_person_id NULL`
- `reviewed_at NULL`
- `review_notes NULL`
- timestamps

### `booking_contract_files`

- `id BINARY(16)` UUIDv7 primary key
- `organization_id BINARY(16)` tenant boundary
- `contract_submission_id BINARY(16)`
- `disk`, `path`
- `original_name`, `mime_type`, `size_bytes`, `sha256`
- `sort_order` for photographed pages
- timestamps

The one-to-many file model is required because a signed return may be a single PDF or many photographed pages.

## File policy

The default limits are configurable in `config/contracts.php` and `.env`:

- template: 20 MiB
- signed file: 20 MiB per file
- signed files: up to 30 files per submission

Template extensions currently accepted: PDF, DOC/DOCX, ODT, JPEG, PNG and WebP.

Signed-copy extensions planned for M4: PDF, JPEG, PNG and WebP.

HTML, SVG and executable formats are intentionally excluded. Malware scanning can be added before production if the deployment accepts files from untrusted public clients.
