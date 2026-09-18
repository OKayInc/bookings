# M7-R20 — numeric questionnaire constraints

Numeric questions can now require an answer to satisfy comparisons with an earlier numeric answer or a fixed number. These are validation rules, separate from M7-R15 display dependencies.

## Configure

Open **Appointment type → Questionnaire → Add or reuse question / Edit**, select **Number**, and use **Numeric answer constraints**.

For `Answer2 >= Answer1`, edit Q2, add a constraint, select **>= Greater than or equal to**, choose **Earlier numeric answer**, and select Q1. Both questions must be on the same appointment type, with Q1 active and positioned before Q2.

Supported operators are `>`, `>=`, `=`, `<=`, `<`, and `!=`. Input aliases `<>` and `!` also mean “different from” and are saved as `!=`. The editor shows one different-from choice with all aliases in its label.

Use **AND** to combine rules within a group, and **OR** to start an alternative group. AND has higher precedence than OR. The editor shows a parenthesized preview, for example:

```text
(this answer >= “Minimum” AND this answer < “Maximum”) OR (this answer = 0)
```

The first connector has no effect. Up to 100 ordered rules are supported. Repeat a predicate in another OR group when needed; arbitrary nested parentheses are not an editor feature.

## Validation behavior

- The form checks constraints as either answer changes and displays feedback beside the constrained question. The server also checks live quotes and final booking submissions, including submissions without JavaScript.
- Hidden target questions are excluded from validation, pricing, and saved answers as before. Blank optional targets are allowed; required targets still need an answer.
- Missing, hidden, or nonnumeric source answers fail that individual comparison, including `!=`. A different OR group may still satisfy the expression. Missing values never become zero.
- Existing minimum/maximum, required, and whole-number pricing rules remain in force. Existing browser step controls are unchanged.
- Decimal text is compared without binary floating-point rounding. Signed numbers and scientific notation are supported, up to 255 characters and exponents from -1000 to 1000. Invalid or oversized operands cannot satisfy a comparison.
- Source questions cannot be deleted, disabled, changed away from Number, or moved to/after a constrained question while referenced. Edit/remove the constraints first. References to another appointment type or organization are rejected.
- Constraints are specific to the appointment-type attachment. They are not copied to reusable templates or newly attached copies; ordinary reusable configuration such as min/max remains reusable.
- Existing questionnaires have no added constraints and keep their previous behavior. Historical bookings and prices are not rewritten.

## Installation

One additive migration creates `appointment_question_numeric_constraints`. Deploy the new public JavaScript asset along with the PHP and views. There are no new dependencies, API keys, or environment settings. See `UPGRADE-M7-R19-TO-M7-R20.md` and `VERIFICATION-M7-R20.md`.
