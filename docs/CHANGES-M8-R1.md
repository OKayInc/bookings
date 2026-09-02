# M8-R1 — ticket configuration safeguards and seating fees

## Ticket-compatible modes

- Enabling **Issue one admission ticket per attendee** immediately changes attendance to **Group** and duration to **Fixed**.
- While ticketing remains enabled, the browser hides and disables **Single** attendance and **Variable** duration.
- Ticketed events may use only **Free** or **Per attendee** pricing. **Fixed total** and **Duration rate** are hidden and disabled until ticketing is unchecked.
- The request validator and ticket event/pricing services independently reject incompatible values, so altered or direct requests cannot bypass the browser behavior.

## Paid seating-block fees

- Paid per-attendee events can configure an optional **Additional fee per ticket** inside each section/row seating block. The field is intentionally in the seating block rather than a separate pricing section because the amount belongs to the allocated block.
- Free events hide the field and server validation rejects submitted seating fees.
- Unassigned and globally consecutive schemes have no section/row blocks and therefore no seating-block surcharge.
- The base per-attendee price and allocated seating fee are added together. Slot choices show the current ticket total and checkout itemizes held seating fees.

## Inventory and price snapshots

- Booking holds now reserve the exact ticket seats and their quoted fees, preventing another buyer from taking a numbered seat during checkout.
- Booking creation revalidates every held allocation under the appointment lock while retaining the server-created held fee.
- Each ticket stores its individual historical seating fee, and booking price lines preserve the itemized seating-fee total.
- A reschedule that would change the seating-fee total is rejected instead of silently repricing a booking before M9 payment adjustments exist.

## Compatibility

Migration `2026_09_01_000062_add_ticket_seat_pricing.php` adds nullable held-seat JSON and a zero-default per-ticket fee. Existing appointments, holds and tickets remain valid with no seating surcharge. M8-R1 adds no Composer dependency, environment variable, queue worker or scheduled command.
