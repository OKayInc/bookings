# M5 Questionnaire Architecture

## Question types

`text`, `textarea`, `checkboxes`, `radio`, `select`, `date`, `time`, `datetime`, `number`, `file`, `email`, `telephone`, `address`.

Questions belong to an appointment type and have explicit ordering, required/active flags and type-specific JSON configuration. Choice questions have unlimited database-backed `question_options` rows.

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
