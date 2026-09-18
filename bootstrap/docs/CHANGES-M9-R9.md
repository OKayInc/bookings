# M9-R9 — organization taxes

M9-R9 makes tax collection an organization-owned part of pricing and payment.

## Organization configuration

- The organization create/edit form asks whether taxes are collected.
- When enabled, a tax ID/registration number, advertised-price mode, and at least one named percentage are required on both the browser and server.
- Administrators can add or remove up to 20 taxes. Names must be unique within the submitted configuration.
- Percentages accept values greater than zero through 100% with up to four decimal places. They are stored as integer millionths rather than floats.
- Disabling tax collection atomically clears the identifier, pricing mode, and active rate rows.

## Calculation order

The authoritative server order is appointment/attendee price, ticket seating, equipment, questionnaire adjustments, distance charges, short-notice fees, refundable deposits, coupon discount, and then tax. Deposits are identified separately and excluded from the taxable base.

Tax-exclusive mode calculates every rate from the same discounted taxable subtotal and adds the tax lines to the final total. Tax-inclusive mode divides the advertised taxable total by one plus the combined rate, then allocates the extracted tax across configured rates while guaranteeing that the tax lines add exactly to the extracted tax total. No floating-point money arithmetic or client-submitted price is trusted.

## Checkout and history

The availability response reflects the final tax-aware price. The booking checkout displays the pre-tax subtotal, each named percentage and amount, whether the amount was included, the final total, and tax ID. The staff and passwordless client booking pages display the same saved breakdown.

Each booking snapshots its subtotal, total tax, mode, tax ID, and ordered tax lines. Organization changes never rewrite historical bookings. Existing bookings receive a zero-tax snapshot whose subtotal equals their existing final price.
