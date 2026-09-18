# M9-R1 — quantity-aware equipment rentals

## Added

- Equipment resources now define physical stock instead of behaving as a single binary-availability row.
- Appointment types define how many pieces of each assigned equipment resource one session needs.
- Overlapping holds and scheduled appointments consume only their reserved quantity; a slot remains bookable while enough stock remains.
- Public slot results show the current free count, total stock and quantity the appointment will reserve.
- Equipment can be free, priced per piece, priced once at a fixed rental fee, or priced through an exact bundle schedule.
- Bundle schedules use the cheapest exact combination. A one-piece tier is required, so every permitted quantity is priceable.
- Equipment charges enter the normal M9 quote, payment, retainer, balance and refund workflow and are preserved as immutable booking price lines.
- Holds and appointments snapshot `quantity_reserved`, so later appointment-type quantity edits do not change an existing allocation.
- Joining an existing group appointment does not allocate the appointment's equipment a second time.
- Quantity management is explicitly enabled per equipment resource. Legacy equipment remains binary and replacement-group compatible; existing equipment already configured above stock one is enabled automatically by the follow-up migration.
- Historical replacement-resource payloads always retain binary one-candidate semantics and bypass piece-quantity validation, even if a resource was subsequently changed to quantity-managed equipment.
- An unused owned resource now shows a guarded **Delete** action. Resources referenced by a hold or appointment retain their history and can only be disabled.
- Number questions now offer an explicit **Answer × rate** charge. The billable answer, after included units, is multiplied by the configured currency rate and supports decimal answers.

## Compatibility

The M9-R1 migrations are additive. Every existing resource receives stock `1`; every existing resource assignment and reservation receives quantity `1`; and every existing equipment assignment defaults to free. Migration `000065` adds the explicit quantity flag and enables it for equipment already configured with stock greater than one. Existing binary resource behavior is unchanged.

Pricing is configured on the appointment-type assignment. This is intentional: one physical resource may be shared between organizations, while each appointment type keeps pricing in its organization's currency.

The numeric rate uses the existing additive questionnaire pricing columns, so no second M9-R1 migration is required. Existing fixed and percentage question charges remain unchanged.
