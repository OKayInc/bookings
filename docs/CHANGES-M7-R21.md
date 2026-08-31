# M7-R21 — compare numeric answers with attendee count

The numeric constraint editor's **Compare with** dropdown now includes **Number of attendees**, alongside **Earlier numeric answer** and **Fixed number**.

## Meaning

The comparison uses the seats reserved for the individual booking, including the primary client. It does not use session capacity, remaining seats, the number of entered attendee names, or seats reserved by other clients. A single-attendee booking has a count of 1.

For example, configure a numeric question named “Meals needed” with `this answer <= Number of attendees`. A booking for 3 people may answer 0 through 3, subject to the question's other settings, but cannot answer 4. No separate attendee-count question is needed.

All existing operators remain available: `>`, `>=`, `=`, `<=`, `<`, and different-from (`!=`, `<>`, `!`). The new operand can be combined with question and fixed-number comparisons using the existing AND/OR grouping and precedence.

## Behavior and safeguards

- Selecting Number of attendees hides and disables the unrelated question/fixed-number inputs. Saved rules and grouped previews retain the selection.
- Browser feedback uses the count rendered from the booking hold. Quotes and final submissions independently use the server's held count, ignoring any replacement count in the request or answers.
- Hidden target questions and blank optional targets retain their M7-R20 behavior. A missing or invalid attendee context cannot satisfy an attendee comparison, including different-from.
- No group-only restriction is imposed: the operand also works for a single-attendee booking.
- Existing question/fixed-number constraints remain compatible. Attachment-specific constraints are still not copied into reusable templates.
- No historical bookings, answers, prices, or attendee counts are changed.

One additive migration introduces nullable `operand_type` on numeric constraints. Existing NULL values retain their original question/fixed-value interpretation. Deploy the updated public JavaScript file with the views; the checkout script URL now includes an M7-R21 cache version. See `UPGRADE-M7-R20-TO-M7-R21.md` and `VERIFICATION-M7-R21.md`.
