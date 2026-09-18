# M9-R2 — multi-answer questionnaire dependencies

## Added

- A display condition sourced from a checkbox question can select several acceptable answers.
- The condition uses **any-answer** semantics: selecting at least one configured answer makes that condition true.
- The condition remains one Boolean predicate, so existing AND groups and OR alternatives retain their meaning.
- Radio and select sources continue to accept exactly one dependency answer.
- The browser and authoritative server evaluator use the same option-set semantics.
- Every referenced option is protected from removal or incompatible question-type changes.

## Compatibility

Migration `2026_09_02_000066_add_multiple_answers_to_question_visibility_conditions.php` creates the normalized condition-option table and backfills every existing dependency. Legacy request and browser payloads containing one `question_option_uuid` remain accepted.
