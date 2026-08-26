# M7 changes

- Google Calendar OAuth connection support per resource.
- Microsoft Outlook/Microsoft 365 OAuth connection support, including personal Microsoft accounts when the Entra app registration permits them.
- Calendar discovery/import and refresh UI.
- Encrypted OAuth access/refresh-token persistence and automatic token refresh.
- Per-appointment-type calendar configuration.
- Multiple selected calendars may participate in availability checks.
- One writable target calendar per resource may receive synchronized appointment/session events.
- Google FreeBusy integration, including fail-closed handling of per-calendar FreeBusy errors.
- Microsoft per-calendar `calendarView` availability integration.
- External calendar conflicts participate in required-resource and optional-resource scheduling.
- Configured calendar/provider failures fail closed for booking safety.
- Short-lived external-busy caching through Laravel cache/Memcached for availability browsing.
- Fresh cache-bypassing external-calendar rechecks during hold/booking/reschedule commits to reduce external-calendar race conditions.
- Appointment event create/update/delete synchronization.
- Booking backend shows external-event synchronization status/errors when mappings exist.
- Five-minute reconciliation command: `appointments:sync-calendars`.
- Regression tests for providers, appointment-type calendar configuration, busy-slot removal, outbound event sync, model-table mappings, and Blade compilation of the new calendar screens.
