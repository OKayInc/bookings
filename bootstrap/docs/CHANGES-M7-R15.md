# M7-R15 changes

M7-R15 adds server-enforced dependent questions to appointment-type questionnaires.

## Dependency editor

- Every attached appointment question has an optional **Display dependencies** editor.
- A predicate selects one earlier checkbox, radio, or select question and one of its active answers.
- Any number of predicates can be joined with AND or OR, up to the request safety limit of 100 rows.
- Standard Boolean grouping applies: AND binds within a group and OR begins an alternative group.
- Repeating the same question/answer predicate is rejected.
- Source questions must be active, belong to the same appointment type, and appear before the dependent question.
- Referenced source questions/options cannot be disabled, reordered into an invalid sequence, changed to a non-choice type, or removed until dependents are updated.

## Public questionnaire behavior

- Conditional questions update immediately when a choice changes.
- A hidden question is disabled and its stale browser value is cleared.
- Chained dependencies evaluate from top to bottom because all sources are earlier questions.
- Required settings return when a question becomes visible again.
- Live quote requests omit disabled hidden answers.

## Authoritative server behavior

Visibility is recalculated from submitted option UUIDs on every quote and final submission. Hidden answers are ignored even if a client bypasses JavaScript and submits them directly. Hidden questions do not participate in:

- required/type/option validation;
- email, telephone, address, or driving-route verification;
- file validation or persistence;
- option, number, or driving-distance pricing; or
- immutable `booking_answers` rows.

## Reusable questions and option identity

Dependencies stay on the appointment-type attachment rather than the reusable organization template, because each predicate refers to another attachment in the same ordered questionnaire. Attaching a reusable question therefore begins with no dependencies.

Choice-option updates now preserve existing option UUIDs. This keeps valid dependencies attached when an answer label, machine value, order, or price changes. Removing an answer used by a dependency is blocked transactionally.

## Database

Migration `2026_08_29_000055_create_appointment_question_visibility_conditions.php` creates the ordered relational condition table. No Composer package, environment variable, queue, or scheduled-command change is included.
