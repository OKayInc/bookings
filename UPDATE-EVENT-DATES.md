# Fixed ticketed events and appointment-type form changes

Based on GitHub commit 5799735d1a685337ea2c5ded30bb6a0a37aa9b48.

## Install

1. Back up your project and database, then copy these project files into the installation. Keep your existing `.env` and uploaded files.
2. Run `composer install --no-dev --optimize-autoloader` if dependencies are not already installed.
3. Run `php artisan migrate --force`.
4. Run `php artisan optimize:clear`.

The archive excludes local environment files, dependencies, generated caches and uploads.

## Changes

- Free pricing hides the whole Payment collection and refunds section. Hidden payment settings use full collection defaults; no stale retainer requirement blocks saving. Separately configured equipment charges still use the existing pricing system.
- Ticketing requires an Event date and Doors open at time, interpreted in the organization's timezone.
- Booking notice and Booking season are hidden and disabled for ticketed types; their restrictions are ignored by ticket availability.
- Availability offers only the fixed event start, including start times outside the ordinary slot increment. Resource availability, holidays, capacity, existing ticket seating and private-event approval checks remain in effect.
- The public date picker defaults to the event date in the client's timezone.
- Date/time/venue changes are rejected while there are future scheduled appointments or active booking holds. Existing ticket snapshots are preserved.

## Database and existing events

Migration 000076 creates `event_occurrences`, with a many-to-one appointment-type relationship, UTC start, timezone, venue and active status. Appointments reference their occurrence; bookings and tickets retain their existing appointment relationships. This allows future multi-date/multi-venue support without adding date columns to appointment types. The current editor manages one event at a time.

Existing ticketed appointments are backfilled into occurrences and linked without changing their ticket snapshots. If a ticketed type has no existing appointment, open its editor and set its event date and doors-open time before accepting bookings. No date is guessed for an unscheduled event.

The existing event-location input is saved as the occurrence venue, and location disclosure continues to follow the existing privacy settings. Scheduling an event still requires matching organization/type and required-resource availability.

## Verification

67 focused tests passed (375 assertions) on PHP 8.4.24 and MariaDB 10.11.14, covering ticketing, private-event approval, fixed-date validation, timezone conversion, nonexistent daylight-saving times, booking holds, availability, payment/equipment behavior, appointment configuration, seasons and Blade compilation.

The rendered edit form also passed JavaScript DOM checks for pricing/ticketing toggles, restored season controls, and disabled hidden fields. A full visual browser check was unavailable because the browser download timed out.
