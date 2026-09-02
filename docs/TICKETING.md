# Ticketing

M8 makes ticketing an optional appointment-type capability. M8-R1 adds strict ticket-compatible mode enforcement and paid seating-block fees. Ticketing reuses the existing shared group-session capacity, MariaDB booking holds and appointment lock instead of creating a second inventory system.

## Event timing

- Enabling **Issue one admission ticket per attendee** requires group attendance and a fixed duration. The administration form forces those values and hides single attendance and variable duration until ticketing is disabled.
- The selected appointment start means **doors open**. It remains the beginning of the appointment and resource-busy range.
- **Show starts** is a required minute offset from doors open.
- **Show ends** is an optional minute offset from doors open.
- Both advertised show times must be inside the appointment range; show end cannot precede show start.
- The actual UTC show times are snapshotted on the shared `appointments` row. Later appointment-type edits do not rewrite an already-created event.

The appointment end remains operationally important even when show end is displayed: resources continue to be unavailable through appointment end plus the configured after-buffer.

## Seating schemes

| Scheme | Ticket label | Configuration |
|---|---|---|
| Unassigned | General admission | Capacity alone defines inventory. |
| Consecutive | Seat 1, Seat 2, … | Automatically spans 1 through event capacity. |
| Section + seat | Section Floor · Seat 12 | One or more section blocks with consecutive numeric ranges. |
| Row + seat | Row A · Seat 12 | One or more row blocks with consecutive numeric ranges. |
| Section + row + seat | Section Balcony · Row B · Seat 12 | Section and row are required; each block has a numeric range. |

For **section + seat** and **row + seat**, the administrator may allow the seat component to be omitted. An unnumbered block then uses a quantity, such as 50 general-admission tickets in Section Floor. Numbered and unnumbered blocks may coexist. All blocks must total exactly the event capacity and may not overlap.

Seats are allocated automatically in configuration order. Each shared appointment snapshots its scheme and blocks, so every buyer joining that event uses the same inventory definition.

## Pricing and seating fees

Ticketed events may use only **Free** or **Per attendee** pricing. Fixed-total and duration-rate pricing are hidden while ticketing is enabled and are independently rejected by the server. The existing flat, absolute-range and accumulative-range per-attendee calculations remain available for the base ticket price.

For a paid event, each section/row seating block may add an optional fee per allocated ticket. This fee is stored in the organization's currency and added on top of the base per-attendee price. Free events cannot contain seating fees. Consecutive and unassigned schemes do not have section/row blocks and therefore do not add a block fee.

The exact seats and their fees are reserved on the booking hold. Slot choices show the current ticket total, checkout shows the held admissions and itemized seating fees, and the server recalculates the same snapshot before creating the booking. Each resulting ticket keeps its individual seating fee for history. A reschedule is rejected when the destination allocation would change the immutable seating-fee total; changing a settled booking price requires cancellation and a new booking.

Ticket timing, duration, capacity and seating configuration cannot be changed while the appointment type has a future booked event. This prevents later buyers from seeing a different definition than the snapshot used for tickets already sold. The configuration becomes editable again after those events end.

## Ticket lifecycle

One ticket is created for every `booking_attendees` row inside the booking transaction.

| Booking state | Ticket state | Admission |
|---|---|---|
| Email/contract/staff/payment pending | Reserved | Rejected at check-in. |
| Confirmed | Valid | May be checked in once. |
| Cancelled or declined | Voided | Rejected; allocated seat becomes available again. |
| Ticket checked in | Checked in | A second scan is rejected and reports the original time. |

Cancellation keeps the printed section/row/seat labels for history but clears the unique allocation key. Rescheduling is blocked after any ticket in the booking has checked in. Before check-in, the existing ticket codes stay with their attendees and seats are reassigned from the destination event's inventory.

The scheduled pending-booking cleanup follows the same lifecycle: when an unverified guest booking expires and becomes cancelled, its tickets are voided and its allocated seats are released.

Paid tickets remain **Reserved** while their booking is `pending_payment`. A successful full payment or configured retainer moves an otherwise-ready booking to `confirmed`, which automatically makes its tickets valid. An unpaid balance does not invalidate a ticket after the configured retainer has confirmed the booking.

## Printing and admission

The private passwordless booking-management page lists every ticket and opens a print-friendly ticket page. A pure server-side Code 128 SVG is generated from the unique `AT-…` code; no external barcode service or JavaScript dependency is required.

The backend **Scheduling → Ticket check-in** desk accepts a USB/Bluetooth barcode scanner or manual code entry. Any active organization member may operate admission, including employees, but codes are always tenant-scoped to the active organization. Check-in uses a row lock and a POST request, so concurrent or repeated scans cannot admit the same ticket twice. Staff may undo an accidental check-in from the recent activity table.
