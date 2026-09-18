# M3 Revision 1

## Timezone selectors

Availability schedule editing now uses a select control populated with PHP's canonical IANA timezone list instead of a free-text timezone field.

This applies to:

- organization availability schedules
- resource availability schedules
- appointment-type availability schedules
- the availability preview display/booking timezone

The existing `IanaTimezone` server-side validation remains in place.

No database migration is required.
