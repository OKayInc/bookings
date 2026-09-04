# M9-R5 — upcoming bookings dashboard

The authenticated `/dashboard` now places a booking list beneath the organization header and above the existing organization totals. Login continues to use the existing dashboard route.

## List and filters

Each row shows the appointment date and time, appointment type, linked booking details, reference, client name, attendee count, booking status, and payment status. Admission-ticket bookings also show doors-open and show-start times. Multiple bookings for the same group event remain separate rows because their client, confirmation, and payment states may differ.

The top-right controls select the date range and number of bookings per page. Selections submit automatically; the Apply button also works without JavaScript. Changing either selection starts at page 1. Pagination preserves the selections.

The default is **From today to 1 week**, with **10** bookings per page. Available page sizes are 10, 25, 50, and 100.

| Date option | Window in the active organization's timezone |
| --- | --- |
| Today | Until midnight at the start of tomorrow |
| Today and tomorrow | Until midnight after tomorrow |
| From today to 1 week | Seven calendar dates including today; ends at midnight seven days from today |
| From today to next month | Ends at midnight on the corresponding date next month; clamps to its last date when needed |
| All | No upper date limit; results remain paginated |

All ranges include in-progress events, even when they started before today, and exclude events whose scheduled end is at or before the current time. The displayed date interval makes the upper bound visible. Cancelled and declined bookings are included when their scheduled event falls in the selected window. Historical bookings remain available through the existing Bookings page.

Calendar boundaries are computed locally before conversion to UTC, including daylight-saving transitions. Times show timezone abbreviations and overnight events show both dates. Sorting uses appointment start time, with booking reference as a stable tie-breaker.

## Status colours

Statuses always include text; colour is an additional cue.

| Status | Colour |
| --- | --- |
| Confirmed; paid | Green |
| To be confirmed; partially paid | Amber |
| Cancelled; unpaid | Red |
| Declined | Dark |
| In progress; partially refunded | Cyan |
| Refunded; payment waived; no payment required | Grey |

The booking column distinguishes pending email verification, contract review, staff confirmation, and payment below the **To be confirmed** badge. Booking and payment badges are independent: a confirmed booking can still show unpaid or partially paid. Free/fully discounted bookings with no payment/refund history show **No payment required**. Existing schedule warnings and appointment-level cancellation are also shown.

## Access and performance

The list is scoped to the active organization. Owners, administrators, and managers see organization bookings; employees see only bookings associated with their person resources. These are the same rules used by the existing booking detail controller. Assigning several resources to one booking does not duplicate its row.

The query loads only the selected page, eager-loads appointment/type data, and counts schedule warnings without per-row queries. Date range, page size, and page number are validated on the server.

No schema, dependency, payment workflow, booking workflow, or environment setting changes are required.
