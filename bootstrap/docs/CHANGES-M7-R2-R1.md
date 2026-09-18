# M7-R2-R1 changes

- Fixed Google Calendar list refresh returning HTTP 400 `invalidParameter` / invalid boolean.
- Google `calendarList.list` now sends `showHidden=true` as the literal REST query value instead of allowing PHP/Guzzle to serialize native `true` as `1`.
- Added a regression test that inspects the actual outgoing Calendar API request query string.
- No database migration or configuration change is required.
