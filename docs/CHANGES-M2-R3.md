# M2 Revision 3

## Fixed

- Updated `AppointmentContractTemplateTest` for the M2 appointment-type validation contract.
- Contract feature tests now submit a minimal valid M2 appointment-type payload including:
  - attendance mode
  - fixed duration mode
  - duration unit/value
  - before/after buffers
  - free pricing mode
- Added `assertSessionHasNoErrors()` before redirect assertions so validation regressions report clearly.
- Added a reusable `validAppointmentTypePayload()` helper inside the contract test so contract-specific cases do not repeatedly duplicate unrelated M2 fields.

## Not changed

The production `StoreAppointmentTypeRequest` validation remains strict. These fields define required scheduling semantics and should not silently disappear from real requests.

No migrations or database changes are included in this revision.
