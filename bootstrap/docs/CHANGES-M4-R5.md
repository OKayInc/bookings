# M4 Revision 5

## Appointment-type deletion and disabling

Appointment types now expose lifecycle actions in the backend:

- If an appointment type has **zero bookings in its entire history**, it can be permanently deleted.
- If **any booking exists**, regardless of status (including cancelled or declined), permanent deletion is blocked and the UI offers **Disable** instead.
- Disabling preserves all appointments/bookings/history and prevents new public booking access.
- Disabling also releases active temporary booking holds so a previously-open guest browser cannot complete a new booking after the type has been disabled.

Deletion is enforced server-side in `AppointmentTypeDeletionService`; it is not merely a Blade/UI check.

When an unused appointment type is permanently deleted, M4-R5 also cleans up appointment-type availability schedules and best-effort deletes its logo and all contract-template files from storage. MariaDB cascading foreign keys remove the remaining appointment-type-owned rows such as resource pivots, invitations, holds, orphan sessions and contract-template database records.

No database migration is required.
