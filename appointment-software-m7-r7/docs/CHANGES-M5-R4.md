# M5 Revision 4

## Address coordinate type normalization

`AddressValidationService` now normalizes Google Address Validation latitude and longitude values to PHP `float` values whenever coordinates are present.

This avoids type drift caused by JSON serialization, where a coordinate such as `45.0` can be decoded as integer `45`. The persisted/returned questionnaire metadata now has a stable coordinate type.

No migration or database change is required.
