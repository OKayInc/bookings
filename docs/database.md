# Database — M3

## Core M1 tables retained

- `persons`
- `users`
- `organizations`
- `organization_memberships`
- `resources`
- `appointment_types`
- `appointment_type_resources`
- `appointment_contract_templates`

All entity primary keys continue to use UUIDv7 encoded as `BINARY(16)`.

## M7-R5 organization-member invitations

`organization_member_invitations` provides account onboarding before an `organization_membership` can reference a person:

- `organization_id`, `invited_by_person_id`, and nullable `accepted_by_person_id` use UUIDv7 `BINARY(16)` keys;
- `email` retains the addressed value and `email_normalized` enforces one invitation record per organization/recipient;
- `role` accepts non-owner backend roles;
- `token_hash BINARY(32)` stores SHA-256 output, never the raw emailed token;
- `expires_at_utc`, `accepted_at_utc`, and `revoked_at_utc` enforce and audit invitation lifecycle.

Acceptance creates an active row in the existing `organization_memberships` table. It does not create an organization for the invitee.

## appointment_types — M2/M3 configuration

M2 extends `appointment_types` with configuration fields rather than creating a separate table for every small setting. These values define a service/event type; actual scheduled sessions and bookings arrive in M4.

### Attendance

- `attendance_mode`: `single` or `group`
- `capacity`: maximum total attendees in one scheduled session; `1` for single mode

### Duration

- `duration_mode`: `fixed` or `variable`
- `duration_unit`: `minute`, `hour`, `day`, `week`
- `duration_value`: fixed duration value
- `minimum_duration_value`, `maximum_duration_value`, `duration_increment_value`: variable-duration range/increment

Days/weeks are configuration units. M3 calculates actual start/end instants with timezone-aware calendar arithmetic rather than assuming every civil day is exactly 86,400 seconds.


### Start-time interval

- `start_interval_minutes`: candidate appointment starts are aligned to this interval independently of appointment duration. Defaults to 15 minutes.

### Buffers

- `buffer_before_minutes`
- `buffer_after_minutes`

These are scheduling blocks, not client-visible appointment duration.

### Pricing

- `pricing_mode`: `free`, `fixed`, `rate`
- `fixed_price_minor`: integer currency minor units
- `rate_amount_minor`: integer currency minor units
- `rate_unit`: `minute`, `hour`, `day`, `week`

Money is never stored as floating-point values.

### Workflow/presentation configuration

- `requires_resource_confirmation`
- `redirect_url`
- existing `logo_path`
- existing contract-template relationship

The resource-confirmation boolean is configuration only in M2. M6 implements confirmation records and email accept/deny behavior.

## appointment_type_invitations

Invite-only access records:

- `id BINARY(16)` — UUIDv7
- `organization_id BINARY(16)` — explicit tenant boundary
- `appointment_type_id BINARY(16)`
- `created_by_person_id BINARY(16) NULL`
- `token_hash CHAR(64)` — SHA-256 of the raw invitation token; raw token is shown once and not stored
- `recipient_email VARCHAR(254) NULL`
- `expires_at DATETIME(6) NULL` — stored as UTC
- `max_uses INT UNSIGNED NULL`
- `uses_count INT UNSIGNED`
- `is_active BOOLEAN`
- microsecond timestamps

M2 verifies access but does not increment usage. M4 increments `uses_count` transactionally only when a booking using the invitation is committed.

## Contract templates

`appointment_contract_templates` remains versioned/private. M4 will add booking-linked signed-contract submissions and private files for manual review.

## Time policy

Canonical instants use UTC `DATETIME(6)`. IANA zones are stored separately where civil-time context matters. MariaDB named-zone conversion is available through timezone tables populated with `mariadb-tzinfo-to-sql` and `CONVERT_TZ()`.

## M3 availability tables

### `availability_schedules`

Stores organization/resource/appointment-type schedule scopes, IANA timezone and active state. Scope uniqueness is enforced by `(organization_id, scope_type, scope_id)`.

### `availability_rules`

Stores recurring weekday-local time intervals for a schedule. Multiple non-overlapping intervals per weekday are allowed.

### `availability_exceptions`

Stores absolute UTC intervals that either add availability or subtract it. The IANA timezone used when entering the exception is retained.

### `booking_holds`

Stores temporary, expiring reservations before a booking is committed. It includes actual appointment start/end, buffer-expanded blocked start/end, selected booking timezone and duration value. Only a SHA-256 hash of the raw hold token is stored.

### `booking_hold_resources`

Links a hold to all assigned resources whose rows participate in pessimistic locking/conflict checks.

M4 will add persistent session/booking/resource-reservation records and extend the same conflict engine beyond temporary holds.

## M4 persistent booking tables

M4 added `appointments`, `appointment_resources`, persistent `bookings`, `booking_attendees`, signed-contract submissions/files, and extended booking holds. `bookings.price_minor` is the final authoritative booking price.

## M5 questionnaire tables

### `appointment_questions`

Question definition, ordering, required/active state, type-specific JSON configuration, and optional number-field pricing rule. UUIDv7 primary key stored as `BINARY(16)`.

### `question_options`

Options for checkbox/radio/select questions, including deterministic order and optional fixed/percentage surcharge.

### `booking_answers`

Historical snapshot of the question UUID, label, type, raw/display answer and normalized verification metadata. The FK to the current question is nullable so historical answers remain meaningful if a definition is later removed.

### `booking_answer_files`

Private uploaded answer files with original filename, MIME type, byte size and SHA-256. Files are served only through authorized booking routes.

### `booking_price_lines`

Immutable pricing breakdown captured when the booking is committed. Includes base appointment price and each questionnaire-driven surcharge.

### `bookings.base_price_minor`

M5 adds the base duration/service price separately from `price_minor`. Existing bookings are backfilled so `base_price_minor = price_minor`; new bookings store the base price and final questionnaire-adjusted total independently.
