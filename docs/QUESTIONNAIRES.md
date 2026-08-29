# M5 Questionnaire Architecture

## Question types

`text`, `textarea`, `checkboxes`, `radio`, `select`, `date`, `time`, `datetime`, `number`, `file`, `email`, `telephone`, `address`.

Reusable question templates belong to an organization. An appointment type attaches an independent copy with explicit ordering, required/active flags and type-specific JSON configuration. Choice questions have unlimited database-backed option rows in both the reusable template and attached copy.

The questionnaire builder lists and searches active reusable questions before the create form. Attaching an existing question copies its label, help text, placeholder, type-specific validation, pricing rule, and options. The appointment-type copy can then be edited without silently changing other appointment types. An editor may explicitly update the reusable template so future attachments use the revised definition; existing attachments remain unchanged.

## Display dependencies

An appointment-type question may depend on selected answers from earlier checkbox, radio, or select questions. A condition compares one source question to one source option. Multiple rows use normal Boolean precedence: consecutive AND rows form one group, while OR begins another alternative group. For example, the ordered rows `Q1=A`, `AND Q2=B`, `OR Q1=C` mean `(Q1=A AND Q2=B) OR Q1=C`.

Only earlier questions may be referenced. This provides a progressive top-to-bottom questionnaire, permits dependency chains, and prevents self-references and cycles. Dependencies belong to the appointment-type copy and are intentionally not part of a reusable template because their source questions exist only within that particular appointment type.

The browser hides, disables, and clears a dependent answer as soon as its expression becomes false. This is a convenience layer, not the security boundary. The same expression is evaluated on the server before validation and pricing. A hidden question:

- is not required or provider-verified;
- cannot contribute option, number, or driving-distance fees;
- has no uploaded files processed; and
- does not create a booking-answer snapshot, even if a client submits a forged/stale value.

Source option UUIDs remain stable when their labels or values are edited. An option or source question that is still referenced cannot be removed, disabled, moved after its dependent question, or changed to a non-choice type until the dependency is updated.

## Verification

- Email: RFC syntax plus MX/A/AAAA DNS existence check.
- Telephone: Google's libphonenumber metadata via `giggsey/libphonenumber-for-php-lite`; normalized to E.164.
- Address: Google Address Validation API; normalized formatted address, place ID, coordinates and verdict are snapshotted with the answer. M7-R12 address questions may also calculate a driving distance through Google Routes.
- Files: private Laravel storage with original filename, MIME, byte size and SHA-256.

## Price modifiers

Choice options support `none`, `fixed`, or `percentage` surcharges.

Numeric questions support the same surcharge types and can apply `once` or `per_unit`, with an included-unit threshold. Price-bearing number questions are validated as integer quantities.

Percentages are stored as integer basis points to avoid floating-point pricing math. A 25% rule is stored as `2500` basis points.

### Address driving-distance fees

An address question can optionally store a private origin address (point 0) in its type-specific configuration. The origin is available only in the authorized question editor and is not rendered in public booking HTML, returned by the quote endpoint, or copied into booking answer/price-line metadata.

The public quote and final booking submission ask Google Routes for a `DRIVE` route from point 0 to the entered destination and request only `routes.distanceMeters`. Successful results are cached for a short configurable period. A missing key, provider failure, or unroutable destination fails closed with a validation error so a configured fee cannot be silently bypassed.

The fee can be:

- one positive fixed amount for any routable destination; or
- non-overlapping ranges measured in configured kilometers or miles, each with a non-negative fixed amount, plus a required positive per-distance fallback.

Range minimums are inclusive and maximums are exclusive. A blank maximum is open-ended and must be the final range. Google returns integer meters; range thresholds and fallback increments are converted to meters for comparison. Only one range can match one address answer.

When no range matches, M7-R16 charges the fallback amount for every started configured increment in the selected unit. For example, a 12 km route at `$10 per 5 km` uses `ceil(12 / 5) = 3` increments and charges $30. This applies the fallback to the complete route distance, not merely the gap width. An intentional free radius must be represented by an explicit zero-dollar range; uncovered gaps are never implicitly free. A legacy range configuration without a valid fallback fails closed when an uncovered route is quoted or submitted.

Distance fees are added in questionnaire order before any percentage short-notice fee. The answer snapshot retains meters plus the configured display unit/value. A charged `question_distance` price line retains the distance, pricing mode, and either the matched range or fallback increment/count, but never point 0.

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
