# M7-R16 changes

M7-R16 prevents an uncovered driving-distance range from silently producing a free distance charge.

## Range fallback configuration

- Range-mode address questions now require a positive distance increment and a positive fee per increment.
- The increment uses the question's configured kilometers or miles.
- Existing non-overlapping ranges remain authoritative when one matches.
- Explicit zero-dollar ranges remain supported for a deliberate free service radius.
- A gap before, between, or after ranges uses the fallback instead of producing no fee.
- Fixed-distance fee mode is unchanged because it already covers every routable distance.

The fallback charges every started increment against the complete route distance. For example, `12 km` at `$10 per 5 km` is three increments and costs `$30`.

## Fail-closed behavior

New/edited range configurations cannot be saved without a positive fallback. Existing M7-R12–M7-R15 JSON is not modified automatically because the application cannot safely invent a business-specific increment or price. If a legacy range does not cover a quoted route and lacks a valid fallback, the live quote and final submission return a configuration error rather than allowing a zero charge. Fully covered legacy ranges continue working.

## Quotes and immutable history

- The held-time quote applies the fallback after Google Routes returns authoritative meters.
- Final booking submission recalculates the route and fallback independently.
- The `question_distance` price line uses `distance_fallback` and snapshots route meters, display unit/value, fallback increment, amount per increment, and rounded-up increment count.
- The private point 0 remains absent from public HTML, quote JSON, answers, and price-line metadata.
- Questionnaire visibility from M7-R15 still applies: a hidden address question performs no route lookup and contributes no fallback fee.

M7-R16 uses the existing question `configuration` JSON column. It adds no database migration, Composer package, environment variable, queue, or scheduled command.
