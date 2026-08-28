# M7-R11 changes

M7-R11 adds optional tiered short-notice fees based on the time between booking and the selected appointment start. Appointment duration does not affect whether a tier matches.

## Appointment-type configuration

- Managers with appointment-type management access can add any number of short-notice tiers.
- Each tier has a positive threshold in minutes, hours, days, weeks, or calendar months.
- Each tier charges either a positive fixed amount in the organization's currency or a positive percentage.
- Duplicate value/unit thresholds are rejected in request validation and by a database unique constraint.
- Existing tiers are shown on the edit form and can be added, changed, reordered through form submission, or removed.

## Tier selection and calculation

- A tier matches when the appointment starts on or before its threshold deadline calculated from the quote time.
- Exact threshold boundaries are inclusive.
- If multiple tiers match, only the shortest matching threshold is applied; short-notice fees do not stack.
- Mixed units are compared by their actual calculated deadlines, not by editor row order.
- Calendar units use the organization's IANA timezone and calendar-month arithmetic.
- Fixed tiers add their configured minor-unit amount.
- Percentage tiers use integer basis-point arithmetic with half-up rounding and apply after questionnaire extras.

## Guest quote and booking snapshot

- The public appointment page warns that a short-notice fee may apply and labels the slot price as a base total.
- Once a time is held, the live price breakdown includes the selected short-notice fee even when the appointment type has no questionnaire.
- Final booking submission recalculates the fee using the current time, so crossing into a shorter tier during checkout is handled authoritatively.
- The resulting `booking_price_lines` row snapshots the fee label, rule UUID, calculation type, amount, threshold, and calculated UTC deadline.

## Schema and deployment

Migration `2026_08_28_000054_create_short_notice_fee_rules.php` creates `short_notice_fee_rules`. The upgrade is additive and introduces no Composer package, environment variable, queue, or scheduled-command change.
