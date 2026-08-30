# M7-R19 changes

## Group-only per-attendee pricing

Under **Appointment type → Attendance: Group → Pricing: Per attendee**, choose:

| Calculation | Rule | 12 attendees with 1–10 at $2 and 11–20 at $1.50 |
| --- | --- | --- |
| Same rate for every attendee | One configured unit price multiplied by the booking's attendee count | Depends on the flat rate |
| Absolute ranges | The matching range's rate applies to every attendee in this booking | 12 × $1.50 = **$18** |
| Accumulative ranges | Each portion is charged at its range's rate, then portions are added | 10 × $2 + 2 × $1.50 = **$23** |

- Ranges are inclusive whole-number attendee counts. Up to 50 ranges are supported.
- They must start at 1, have no gaps or overlaps, and cover at least the configured session capacity. All unit prices must be positive.
- Invalid stored pricing and arithmetic overflow fail closed; they do not produce a zero charge.
- In absolute mode, crossing into a cheaper range can reduce the total booking price. This is intentional; use accumulative mode to charge earlier portions at their original rates.
- Pricing counts every attendee in the booking, including the primary client, not merely the number of optional names entered.
- The range calculation restarts for each booking. It is not based on the total number of seats sold across a session.

## Client and staff behavior

- Public appointment details show the applicable attendee rates and explain the selected calculation.
- Slot price previews use the requested attendee count. Changing the attendee count or other booking filters clears stale slots and prices.
- Checkout includes a price card even when there are no questionnaire questions or short-notice fees.
- Quotes and final bookings use the attendee count saved in the hold. Submitted prices or replacement counts cannot lower the charge.
- Saved price lines include each charged portion, quantity, unit rate, calculation mode, and range bounds. Existing bookings retain their historical prices.
- Fixed questionnaire extras keep their existing rules; percentage extras use the attendee base/subtotal. Short-notice fees apply after the questionnaire extras.
- Free, fixed-total, and duration-rate prices are unchanged and are not automatically multiplied by attendee count.
- Per-attendee pricing is unavailable for single attendance. Switching from group to single requires choosing another pricing mode.

## Shared group sessions

Existing behavior remains: multiple clients can reserve seats in the same group session while capacity is available. For example, if capacity is 5 and Person 1 books 2 seats, Person 2 can book the remaining 3. This is shared seating, not an exclusive private-family/party booking mode.

## Upgrade

One additive migration adds the attendee rate, calculation mode, and ranges to appointment types. See `UPGRADE-M7-R18-TO-M7-R19.md` and `VERIFICATION-M7-R19.md`.
