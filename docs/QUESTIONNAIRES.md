# M5 Questionnaire Architecture

## Question types

`text`, `textarea`, `checkboxes`, `radio`, `select`, `date`, `time`, `datetime`, `number`, `file`, `email`, `telephone`, `address`.

Reusable question templates belong to an organization. An appointment type attaches an independent copy with explicit ordering, required/active flags and type-specific JSON configuration. Choice questions have unlimited database-backed option rows in both the reusable template and attached copy.

The questionnaire builder lists and searches active reusable questions before the create form. Attaching an existing question copies its label, help text, placeholder, type-specific validation, pricing rule, and options. The appointment-type copy can then be edited without silently changing other appointment types. An editor may explicitly update the reusable template so future attachments use the revised definition; existing attachments remain unchanged.

## Verification

- Email: RFC syntax plus MX/A/AAAA DNS existence check.
- Telephone: Google's libphonenumber metadata via `giggsey/libphonenumber-for-php-lite`; normalized to E.164.
- Address: Google Address Validation API; normalized formatted address, place ID, coordinates and verdict are snapshotted with the answer.
- Files: private Laravel storage with original filename, MIME, byte size and SHA-256.

## Price modifiers

Choice options support `none`, `fixed`, or `percentage` surcharges.

Numeric questions support the same surcharge types and can apply `once` or `per_unit`, with an included-unit threshold. Price-bearing number questions are validated as integer quantities.

Percentages are stored as integer basis points to avoid floating-point pricing math. A 25% rule is stored as `2500` basis points.

Percentage basis:

- `base_price`: percentage of the original appointment duration/base price.
- `current_subtotal`: percentage of the running subtotal, allowing intentional compounding in configured question/option order.

## Historical integrity

A booking stores:

- final/base amounts on `bookings`,
- line items on `booking_price_lines`,
- question label/type and answer snapshot on `booking_answers`,
- normalized verification metadata on the answer,
- private files on `booking_answer_files`.

Changing a questionnaire later does not rewrite historical bookings. Questions with historical answers are disabled rather than deleted.

Removing an attached question leaves its reusable template available. Existing installations are backfilled with one reusable template for every existing appointment question during the M7-R10 migration.
