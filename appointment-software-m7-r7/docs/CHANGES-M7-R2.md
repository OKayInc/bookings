# M7-R2 changes

M7-R2 is based on the current GitHub repository state at commit `0f90c7a373ea8270d5c6f95f0317f3e4b4fbd777`, not the original M7-R1 archive.

Preserved repository changes:

- `public/.htaccess` added for Laravel front-controller rewriting on Apache hosting.
- Backend email verification signed links can complete without an authenticated Laravel session.
- The corresponding unauthenticated email-verification regression test is preserved.

OAuth reliability changes:

- Google and Microsoft calendar OAuth state is now stored as a short-lived database transaction instead of only in the Laravel session.
- Added `calendar_oauth_states` with hashed state values, initiating user, organization, resource, provider, expiration, and one-time consumption timestamp.
- OAuth callbacks no longer require `auth`, `verified`, or `organization` middleware, so losing/regenerating the browser session during provider consent no longer breaks the callback.
- The callback URL paths are unchanged; existing Google/Microsoft redirect-URI registrations do not need to change.
- The raw OAuth state is never stored; only SHA-256 is persisted.
- OAuth states are single-use and expire after 15 minutes by default.
- Callback re-checks the initiating user's current resource permission before storing provider tokens.
- An authenticated callback belonging to a different backend user is rejected.
- Successful callbacks with no application session redirect to Login; the calendar connection has already been completed safely.
- Old OAuth-state rows are opportunistically pruned.

Configuration:

- Added `CALENDAR_OAUTH_STATE_TTL_MINUTES=15`.

Database:

- Added migration `2026_08_27_000046_create_calendar_oauth_states_table.php`.
