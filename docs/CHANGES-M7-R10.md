# M7-R10 changes

M7-R10 adds an organization-level reusable question library while preserving appointment-specific configuration and immutable historical booking answers.

## Reuse workflow

- The **Add question** screen now displays active reusable questions before the new-question form.
- The list is scoped to the active organization and can be searched by question label or type.
- Questions already attached to the current appointment type are identified and cannot be attached twice.
- Attaching a question copies its type, label, help text, placeholder, required default, validation configuration, number pricing rule, choices, and choice pricing.
- A newly created appointment question is automatically saved as a reusable organization question.

## Independent attachments

- Each appointment type receives an independent `appointment_questions` copy, linked to its reusable source.
- Editing one attachment does not change existing copies on other appointment types.
- The edit form offers an explicit **Update the reusable template for future attachments** option. This updates the library definition and options but does not retroactively rewrite other attachments.
- Removing an unanswered attachment leaves the reusable question available for later attachment.
- Attachments with historical answers retain the existing disable-instead-of-delete behavior.

## Isolation and integrity

- A reusable question can only be listed or attached inside its owning organization.
- A database unique constraint prevents the same reusable question from being attached twice to one appointment type.
- Booking answers continue to reference the appointment-specific question and retain UUID, label, type, value, normalized metadata, and pricing snapshots.
- Existing questions and options are copied into reusable templates during migration; no existing question UUID, option, booking answer, file, or price snapshot is rewritten.

## Schema and deployment

Migration `2026_08_28_000053_create_reusable_questions.php` creates `reusable_questions` and `reusable_question_options`, adds nullable `appointment_questions.reusable_question_id`, backfills existing questionnaire definitions, and adds the organization and attachment integrity constraints. No Composer package, environment variable, queue change, or scheduled command is added.
