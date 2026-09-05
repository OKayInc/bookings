# Equipment inventory and rental pricing

## Inventory

Set a resource's type to **Equipment** and enable **Track a quantity of identical pieces** when the row represents interchangeable stock. Then enter the number of physical pieces available. The stock belongs to the resource itself, including when that resource is shared with another organization.

Equipment with quantity tracking disabled remains a normal binary resource for compatibility and may participate in a replacement group. The appointment-type editor does not offer replacement mode for quantity-managed equipment. For backwards compatibility with existing integrations and historical form payloads, the server still accepts it and treats each replacement candidate as one binary reservation rather than requiring a piece quantity.

When assigning the equipment to an appointment type, enter the number of pieces one scheduled appointment needs. For stock of 20 chairs:

- appointment A may reserve 1 chair;
- an overlapping appointment B then sees 19 of 20 available;
- appointment B may reserve 5, leaving 14 available for other overlapping work;
- any overlapping request for 15 or more is rejected until a hold expires or an appointment releases its allocation.

Buffers participate in the overlap calculation. Active, unexpired holds and scheduled appointments consume inventory; released, expired or cancelled allocations do not. Resource rows are locked in a stable order while a hold is acquired, which serializes competing inventory checks and prevents overselling.

Group booking capacity holds point at an existing appointment. They copy its resource snapshot for audit and notification behavior but do not consume equipment again.

## Rental pricing

Pricing is configured per equipment assignment on an appointment type:

- **Free** adds no charge.
- **Per piece** multiplies the configured unit amount by `quantity_required`.
- **Fixed rental fee** charges one amount regardless of the quantity.
- **Bundle schedule** selects the cheapest exact combination of configured bundle quantities.

A bundle schedule must contain a one-piece tier. For tiers `1 = $3`, `5 = $10`, and `20 = $20`, six chairs cost `$13` and twenty chairs cost `$20`.

Paid equipment must be required. Optional paid equipment is rejected because the current public booking flow does not ask the client to opt into optional equipment, so charging for it would make the displayed price ambiguous.

The checkout quote stores one `equipment_resource` price line per charged resource, including its resource UUID, reserved quantity, pricing mode and unit/bundle breakdown. The total then follows the standard M9 full-payment or retainer workflow and its refund policy.

The rate is intentionally attached to the appointment-type assignment rather than the resource itself. The same shared chairs can therefore be free for one appointment type, `$3` per piece for another, or use a different bundle schedule in another organization and currency.

## Refundable deposits (M9-R6)

Every resource, including equipment without a linked person, can define a default refundable deposit. For quantity-tracked equipment the configured amount is per reserved piece.

A conditional resource requirement on a questionnaire choice can override the default for each assigned resource. A blank override inherits the resource value, an explicit zero removes the deposit for that question assignment, and blank at both levels means zero. The effective deposit is itemized separately from rental revenue and is frozen on the booking so later configuration edits do not change an existing rental.

The booking ledger provides full and partial deposit returns. Partial returns require a reason, and all returns are sent through the same Stripe or PayPal transaction that originally collected the deposit.
