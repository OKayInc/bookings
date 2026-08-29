# M7-R12 changes

M7-R12 adds optional driving-distance pricing to reusable address questionnaire questions.

## Question configuration

- Address questions can enable a private point 0 / origin address.
- The origin appears only in the authorized question editor and is copied with the reusable question definition.
- Organizations choose kilometers or miles for configuration and display.
- A fixed mode adds one positive fee for any route.
- A range mode supports up to 100 non-overlapping ranges with non-negative fixed fees.
- Minimum boundaries are inclusive, maximum boundaries are exclusive, and a blank maximum is open-ended.
- Gaps are permitted and intentionally produce no fee, supporting a free radius.
- Switching a question away from the address type or disabling distance pricing removes the distance configuration from the new definition snapshot.

## Google route calculation

- The existing Google Address Validation API still validates the client answer on final submission.
- A server-side Google Routes `computeRoutes` request calculates a `DRIVE` route between point 0 and the entered address.
- Requests use `TRAFFIC_UNAWARE` for stable distance-based pricing and select only `routes.distanceMeters` through `X-Goog-FieldMask`.
- Successful routes are cached by normalized origin/destination for 15 minutes by default.
- Missing configuration, provider failure, and an empty route fail closed rather than silently omitting a configured fee.

## Quote and history

- A completed address updates the live held-time quote.
- Fixed/range distance fees are applied before M7-R11 percentage short-notice fees.
- Final submission recalculates authoritatively and stores the route meters plus configured display value/unit with the normalized answer.
- Charged routes create a `question_distance` booking price line with distance and selected-range metadata.
- Point 0 is absent from public booking HTML, quote JSON, booking answers, and booking price-line metadata.

## Deployment

M7-R12 uses the existing `configuration` JSON columns on appointment and reusable questions. It has no database migration or new Composer package. It adds optional `GOOGLE_ROUTES_API_KEY` and `GOOGLE_ROUTES_CACHE_SECONDS` environment configuration. When the dedicated routes key is blank or omitted, the application uses `GOOGLE_MAPS_API_KEY`.
