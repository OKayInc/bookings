# M9-R4 — conditional questionnaire resource requirements

## Configuration

- Checkbox, radio and select questions can enable one appointment-type-specific conditional resource rule.
- The administrator selects the trigger answer, the unavailable default answer, a named group, one-of-N or all fulfillment, and one or more normally optional resources.
- Trigger and default answers must be different and remain protected from deletion while referenced.
- Conditional resources must remain assigned and normally optional. Group names cannot collide with permanent replacement groups or another conditional group in the same appointment type.
- One-of-N groups reject quantity-managed equipment; all-resource groups retain the existing quantity reservation.

## Public booking

- Questions remain in their existing ordered questionnaire position after a time is held.
- The selected-time hold is the availability snapshot. One-of-N is offerable when at least one configured member was held; all is offerable only when every member was held.
- An unfulfillable question is hidden and its configured default is inserted by the server before dependency evaluation, quote calculation, validation and booking-answer persistence.
- Forged or stale trigger answers cannot bypass the unavailable default.
- A valid trigger promotes the held candidates before final holiday and external-calendar checks. One-of-N uses the existing replacement-group confirmation semantics; all promotes every member independently.

## Lifecycle

- Existing group appointments can only be promoted; a later booking cannot downgrade a requirement established by an earlier booking.
- Client reschedule results are filtered by saved conditional answers.
- Reschedule execution and staff schedule proposals reapply the saved answer to their new hold and fail closed if it cannot be fulfilled.
- Resource, option and appointment-assignment guards prevent active rules from becoming orphaned.

## Schema

Migration `2026_09_03_000067_add_conditional_resource_requirements.php` creates:

- `appointment_question_resource_rules`;
- `appointment_question_resource_rule_resources`.

The migration is additive and does not alter existing questions, resources, holds, appointments or booking answers.
