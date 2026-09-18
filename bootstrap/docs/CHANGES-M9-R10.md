# M9-R10 — visual availability analysis

M9-R10 turns the backend availability preview into a diagnostic view that explains why a day does or does not contain free starts.

## Timeline

- The result row visualizes the authoritative slots returned by the existing availability engine.
- Organization default hours and all scheduled organization activity provide day-level context.
- The selected appointment type has its own row using its effective schedule and seasonal rules.
- Directly required resources use blue availability and red blocked periods.
- Replacement groups show an aggregate **one of N required** row followed by their individual candidate resources.
- Administrators can opt in to optional-resource rows, which use purple and cannot block the base appointment.
- The chart remains a full readable day on narrow screens through horizontal scrolling and includes keyboard-focusable segments.

## Explanations

Each timeline segment has a local-time explanation, and every row exposes an expandable list of its blocked periods. Diagnostics cover:

- missing, disabled, inherited, and custom schedules;
- weekly hours plus availability/blackout exceptions;
- inactive appointment types and resources;
- seasonal appointment-type limits;
- organization and per-resource holiday closures;
- scheduled appointments and active booking holds;
- connected-calendar busy or fail-closed periods;
- quantity-managed equipment shortages; and
- cross-organization conflicts on a shared resource without exposing the other organization's appointment type.

The requested appointment duration and both buffers participate in the analysis. No duplicate slot policy was introduced: `AvailabilityAnalysisService` obtains the result from `AvailabilityService` and reuses its resolved schedule intervals for explanations.

## Persistence

This release has no database migration or new persisted setting. The optional-resource checkbox is a preview query option only.
