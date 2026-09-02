# Database — M9

## M9 payment ledger

Migration `2026_09_02_000063_add_m9_payments.php` adds one optional encrypted `organization_payment_settings` row per organization and tenant-scoped `payment_rules`. Appointment types store future full/retainer and cancellation-refund terms; bookings snapshot the applicable collection mode, initial amount, balance due instant, refund percentages, matched allowlist rule and aggregate payment status/totals.

`payment_transactions` is the immutable checkout/capture ledger. It records provider, purpose, requested minor-unit amount/currency, opaque return-token hash, provider references, response payload and completion timestamps. `payment_refunds` allocates each refund to its original transaction and preserves a provider idempotency key. `payment_webhook_events` deduplicates each organization/provider event ID and retains processing errors for provider retries. All new entity keys are UUIDv7 `BINARY(16)` and tenant-owned rows cascade with their organization or booking.

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

## M7-R21 attendee-count operands

Migration `2026_08_31_000060_add_numeric_constraint_operand_type.php` adds nullable `operand_type VARCHAR(16)` to `appointment_question_numeric_constraints`. New rules explicitly store `question`, `value`, or `attendee_count`. An attendee-count rule has both `source_question_id` and `comparison_value` NULL; its comparison value is resolved from the booking hold at runtime, never stored as a fixed count. Existing NULL operand types retain M7-R20's inference: a non-NULL source means `question`, otherwise `value`. No backfill or historical booking rewrite is needed.

## M7-R20 numeric question constraints

Migration `2026_08_31_000059_create_appointment_question_numeric_constraints.php` adds ordered attachment-specific validation predicates. `appointment_question_id` is the numeric target; nullable `source_question_id` identifies an earlier active numeric question from the same appointment type. Alternatively, nullable `comparison_value VARCHAR(255)` stores a fixed decimal as text without float rounding. Exactly one operand is required by application validation. `comparison_operator VARCHAR(2)` stores `>`, `>=`, `=`, `<=`, `<`, or canonical `!=`. `boolean_operator VARCHAR(8)` stores `and`/`or`, and target/position is unique.

The UUID primary key and both question relationships are `BINARY(16)`. Foreign keys cascade when an appointment type or organization is deleted; individual referenced-source removal is blocked by application validation. Questionnaire writes lock the appointment-type row, validate the full configuration, and roll back invalid changes. Rules are not copied to reusable templates, and no existing answers/bookings are rewritten.

## M7-R19 per-attendee pricing

Migration `2026_08_30_000058_add_attendee_pricing_to_appointment_types.php` adds nullable `attendee_price_minor BIGINT UNSIGNED`, `attendee_pricing_mode VARCHAR(16)` defaulting to `flat`, and nullable `attendee_price_ranges JSON`. The existing `pricing_mode` string accepts `per_attendee` only for group attendance.

The attendee calculation is `flat`, `absolute`, or `accumulative`. Range JSON contains integer `min_attendees`, `max_attendees`, and `unit_amount_minor` values. Inclusive ranges must start at 1, be contiguous without overlaps, and cover at least the session capacity. Existing `booking_price_lines` snapshot each charged portion, its quantity, unit price, and range metadata; no historical prices are rewritten.

## M7-R18 appointment-type booking seasons

Migration `2026_08_30_000057_add_seasonal_availability_to_appointment_types.php` adds an optional date policy directly to `appointment_types`:

- `seasonal_availability_enabled BOOLEAN` defaults to false, preserving year-round behavior;
- nullable `season_start_date DATE` and `season_end_date DATE` store inclusive organization-local calendar dates;
- nullable `season_recurrence VARCHAR(16)` stores `once` or `yearly`.

For yearly recurrence, the reference years distinguish an ordinary same-year window from a season crossing New Year. Runtime evaluation projects its month/day boundaries into the requested year and requires the complete appointment interval to fit.

## M7-R17 online conference settings

Migration `2026_08_30_000056_add_online_conference_settings.php` creates one optional `organization_conference_settings` row per organization. Google questionnaire API keys, provider secrets, refresh tokens, and the reusable custom URL use Laravel encrypted casts, while non-secret provider identifiers remain queryable. The row cascades when its organization is deleted.

Migration `2026_09_01_000061_add_ticketing_capabilities.php` adds ticket configuration to `appointment_types`, immutable event timing/seating snapshots to `appointments`, and `tickets`. Each ticket belongs to one booking attendee, uses a globally unique printed code, and may hold one appointment-scoped `seat_key`; voiding clears only the allocation key so the historical section/row/seat labels remain visible while inventory becomes reusable.

Migration `2026_09_01_000062_add_ticket_seat_pricing.php` snapshots the exact ticket seats and seating fees on `booking_holds` and stores the individual fee on each resulting ticket. Seating-block fee definitions remain inside the existing appointment-type and appointment seating JSON snapshots.

`appointment_types.is_online` and `meeting_provider` define future meeting behavior. `appointments` snapshot the chosen provider and store the external meeting ID, encrypted attendee/host URLs, provisioning status, and a staff-visible error. Editing an appointment type or rotating organization credentials therefore does not silently switch the provider on an existing scheduled appointment.

## M7-R14 multi-day availability evaluation

M7-R14 adds no schema object. The slot engine now distinguishes the requested start range from the schedule/conflict coverage range. Candidate starts remain inside the requested date, while recurring schedules, exceptions, holds, appointments, holiday closures, and calendar busy periods are evaluated through the possible appointment end plus its configured buffers. A `23:59` weekly-rule end is normalized in memory to the following midnight so adjacent `00:00` availability can merge; stored `TIME` values are unchanged.

## M7-R13 organization deletion lifecycle

M7-R13 adds no schema object. It coordinates existing ownership and foreign-key relationships through an owner-only application service:

- bookings are deleted before contacts, appointment types, appointments, and contract templates protected by restrictive relationships;
- resources owned elsewhere lose only the deleting organization's `organization_resources` row;
- resources owned by the deleting organization are detached from all organizations and have external appointment-type, availability, and calendar configuration removed before the resource row is deleted;
- remaining organization-owned rows cascade through their existing organization foreign keys, while `users.active_organization_id` becomes `NULL` through its existing null-on-delete key;
- file metadata is collected inside the transaction, while physical file deletion occurs after commit.

This is intentionally a hard delete rather than a soft-delete/archive model. Historical booking/resource pivots that reference an organization-owned resource follow their existing resource cascade, while the other organization's booking and appointment rows remain.

## M7-R5 organization-member invitations

`organization_member_invitations` provides account onboarding before an `organization_membership` can reference a person:

- `organization_id`, `invited_by_person_id`, and nullable `accepted_by_person_id` use UUIDv7 `BINARY(16)` keys;
- `email` retains the addressed value and `email_normalized` enforces one invitation record per organization/recipient;
- `role` accepts non-owner backend roles;
- `token_hash BINARY(32)` stores SHA-256 output, never the raw emailed token;
- `expires_at_utc`, `accepted_at_utc`, and `revoked_at_utc` enforce and audit invitation lifecycle.

Acceptance creates an active row in the existing `organization_memberships` table. It does not create an organization for the invitee.

## M7-R6 organization holiday closures

`organization_holidays` stores optional closed-day rules within an organization tenant:

- `organization_id BINARY(16)` owns the rule and cascades deletion with the organization;
- nullable `preset_key` makes common presets idempotent per organization;
- `name` is the organization-facing holiday label;
- `rule_type` is `fixed_annual`, `easter_relative`, `nth_weekday`, or `one_time`;
- rule-specific fields are `month`, `day`, `weekday`, `occurrence`, `easter_offset_days`, and `specific_date`;
- `is_active` allows an organization to stop honoring a configured holiday without deleting it.

Rule dates are resolved in application code using the organization's IANA timezone. Movable Easter dates use the Gregorian computus and do not require the PHP calendar extension.

## M7-R7 regional holiday settings

Migration `2026_08_28_000051_add_regional_holiday_settings.php` adds:

- nullable `organizations.holiday_region`, the explicit country/subdivision used by the organization holiday picker;
- nullable `organization_holidays.region_code` and `provider_holiday_key` for `regional_calendar` rules;
- `organization_resources.enforce_holidays` and nullable `organization_resources.holiday_region`, stored per organization-resource relationship.

Regional organization holidays still require explicit selection and use the existing `preset_key` uniqueness constraint. Resource enforcement is broader by design: when enabled, all official/bank holidays returned for that resource region are availability closures. The resource timezone defines the local date boundary.

## M7-R9 replacement resource snapshots

Migration `2026_08_28_000052_add_replacement_resource_groups.php` adds nullable `replacement_group VARCHAR(80)` to:

- `appointment_type_resources`, where resources with `requirement_mode = replacement` and the same normalized group name form a required 1-of-N group;
- `booking_hold_resources`, which snapshots the available candidates reserved during checkout;
- `appointment_resources`, which preserves the candidates and then the accepted resource independently of later appointment-type edits;
- `resource_confirmations`, which lets workflow status evaluate an acceptance or exhaustion per group.

Replacement candidates retain `is_required = true`; `replacement_group` changes the requirement from “every row” to “at least one row in this group.” Existing rows receive `NULL` and keep their prior required/optional behavior. `resource_confirmations.status` also accepts `superseded`, displayed as **Not needed**, when another candidate fills the same group.

## appointment_types — M2/M3 configuration

M2 extends `appointment_types` with configuration fields rather than creating a separate table for every small setting. These values define a service/event type; actual scheduled sessions and bookings arrive in M4.

### Attendance

- `attendance_mode`: `single` or `group`
- `capacity`: maximum total attendees in one scheduled session; `1` for single mode

### Online meeting

- `is_online`: whether new appointments require an online meeting;
- `meeting_provider`: `google_meet`, `microsoft_teams`, `zoom`, `webex`, `jitsi`, or `custom` when online; otherwise `NULL`.

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

### `short_notice_fee_rules`

M7-R11 stores optional appointment-type pricing tiers separately from the base pricing fields:

- `appointment_type_id BINARY(16)` owns the rule and cascades deletion with the appointment type;
- `threshold_value` plus `threshold_unit` (`minute`, `hour`, `day`, `week`, or `month`) define how soon the appointment may start for the rule to match;
- `adjustment_type` is `fixed` or `percentage`;
- `fixed_amount_minor` stores a fixed fee in integer currency minor units;
- `percentage_bps` stores a percentage in basis points;
- `position` preserves editor order and `is_active` supports future rule lifecycle changes;
- a unique constraint prevents the same value/unit threshold from being configured twice for an appointment type.

When several rules match, pricing uses the rule whose calculated deadline is earliest—the shortest actual notice threshold in the organization's timezone. The resulting booking price line stores the rule UUID and threshold metadata, so later configuration edits do not rewrite the historical charge.

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

Appointment-type question attachment containing an independent definition snapshot, ordering, required/active state, type-specific JSON configuration, and optional number-field pricing rule. M7-R10 adds a nullable link to its reusable organization template. M7-R12 uses the existing JSON configuration for an address question's private origin, display unit, fee mode, and fixed/range amounts. M7-R16 adds `distance_pricing.fallback.increment` and `distance_pricing.fallback.amount_minor` to range-mode configuration; no schema column is added. UUIDv7 primary key stored as `BINARY(16)`.

### `question_options`

Options for checkbox/radio/select questions, including deterministic order and optional fixed/percentage surcharge.

### `appointment_question_visibility_conditions`

M7-R15 stores appointment-type-specific display dependencies as ordered relational predicates:

- `appointment_question_id` is the dependent/target question;
- `source_question_id` is an earlier checkbox, radio, or select question on the same appointment type;
- `question_option_id` is the source answer that must be selected;
- `boolean_operator` is `and` or `or`, and `position` preserves expression order.

AND binds within a group and OR separates alternative groups. The first connector is normalized to AND and has no effect. All three UUID relationships use `BINARY(16)` keys and cascade when their owning questionnaire data is deleted. Application validation requires an active source and option, strictly earlier ordering, same-appointment-type ownership, and no duplicate predicate. Option UUIDs are preserved during ordinary option edits so a label/value change does not retarget a dependency.

Dependencies belong to the appointment-type attachment rather than its organization-wide reusable template because they reference other attachments in one ordered questionnaire. A newly attached reusable question therefore starts without display dependencies.

### `reusable_questions`

Organization-scoped reusable question templates. Stores the default required state, question definition, validation configuration, optional number-field pricing rule, and any M7-R12 address distance-pricing configuration. Templates are copied into `appointment_questions` when attached.

### `reusable_question_options`

Reusable checkbox/radio/select choices and their optional fixed/percentage surcharge. Options are copied into `question_options` when their template is attached.

### `booking_answers`

Historical snapshot of the question UUID, label, type, raw/display answer and normalized verification metadata. M7-R12 adds driving meters and the configured display unit/value to a distance-priced address's normalized metadata, without the private origin. The FK to the current question is nullable so historical answers remain meaningful if a definition is later removed.

### `booking_answer_files`

Private uploaded answer files with original filename, MIME type, byte size and SHA-256. Files are served only through authorized booking routes.

### `booking_price_lines`

Immutable pricing breakdown captured when the booking is committed. Includes base appointment price, each questionnaire-driven surcharge (including matched distance ranges and M7-R16 per-distance fallbacks), and any matching M7-R11 short-notice fee. A fallback line snapshots its increment, amount per increment, rounded-up block count, route meters, and configured display unit.

### `bookings.base_price_minor`

M5 adds the base duration/service price separately from `price_minor`. Existing bookings are backfilled so `base_price_minor = price_minor`; new bookings store the base price and final questionnaire-adjusted total independently.
