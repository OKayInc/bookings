# M5 Revision 2

## Fixed

- Corrected `AddressValidationServiceTest` to use the real Google Address Validation API response structure.
- `result.address` and `result.geocode` are sibling objects; the previous fixture incorrectly placed `geocode` inside `address`.
- Expanded the regression assertions to cover formatted address, place ID, latitude, longitude, and verdict normalization.

## Runtime impact

There are no application-code, database, migration, or configuration changes in this revision. The production `AddressValidationService` was already reading Google's response structure correctly.
