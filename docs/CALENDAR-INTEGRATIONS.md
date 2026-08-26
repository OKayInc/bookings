# M7 Calendar integrations

M7 integrates organization resources with Google Calendar and Microsoft Outlook / Microsoft 365 calendars.

## Design

The Appointment Software database remains authoritative for appointments and bookings.

External calendars have two configurable responsibilities per appointment type:

1. **Check availability** — busy periods are added to the resource's internal availability constraints.
2. **Create appointment event** — the appointment/session is mirrored into one writable external calendar for that resource.

A resource may check multiple calendars. An appointment type may choose at most one write target per resource. Group/class appointments create one external event for the shared appointment session, not one event per attendee booking.

## Google setup

Create a Google Cloud OAuth 2.0 Web application and enable the Google Calendar API.

Configure this redirect URI exactly:

```
https://YOUR-HOST/calendar-connections/oauth/google/callback
```

Environment:

```
GOOGLE_CALENDAR_CLIENT_ID=
GOOGLE_CALENDAR_CLIENT_SECRET=
GOOGLE_CALENDAR_REDIRECT_URI=https://YOUR-HOST/calendar-connections/oauth/google/callback
```

M7 requests:

- `openid`
- `email`
- `https://www.googleapis.com/auth/calendar.calendarlist.readonly`
- `https://www.googleapis.com/auth/calendar.events`
- `https://www.googleapis.com/auth/calendar.events.freebusy`

Google may require OAuth consent-screen verification for a public production application using Calendar scopes.

## Microsoft setup

Register a Web application in Microsoft Entra ID. To support both Microsoft 365/work-school accounts and personal Outlook.com accounts, configure the app registration accordingly and use the `common` tenant.

Redirect URI:

```
https://YOUR-HOST/calendar-connections/oauth/microsoft/callback
```

Environment:

```
MICROSOFT_CALENDAR_CLIENT_ID=
MICROSOFT_CALENDAR_CLIENT_SECRET=
MICROSOFT_CALENDAR_TENANT=common
MICROSOFT_CALENDAR_REDIRECT_URI=https://YOUR-HOST/calendar-connections/oauth/microsoft/callback
```

M7 requests delegated scopes:

- `openid`
- `profile`
- `email`
- `offline_access`
- `User.Read`
- `Calendars.ReadWrite`

`offline_access` is required for a refresh token with the Microsoft v2 authorization endpoint.

M7 uses `/me/calendars` to discover calendars and `calendarView` per selected calendar for busy intervals. This works for personal Microsoft accounts as well as work/school accounts and lets the appointment type select specific calendars rather than only the mailbox-wide schedule.

## Connecting a resource

Backend:

`Scheduling -> Calendar connections`

Each organization resource can connect one Google account and one Microsoft account. A linked staff member may connect their own resource; owners/admins/managers may manage all resource connections.

After OAuth, M7 imports all calendars exposed by the account and records whether each calendar is writable.

## Configuring an appointment type

Open an appointment type and select **Calendars**.

For each assigned resource:

- select zero or more calendars under **Check availability**;
- optionally select one writable calendar under **Create appointment event**.

A calendar can perform both roles.

## Availability failure behavior

If no external calendars are selected, external services have no effect on availability.

If an explicitly selected required-resource calendar cannot be checked because its provider API/token is failing, M7 fails closed for that interval. It does not expose a slot whose external availability is unknown. The connection page shows the provider error.

External busy results are cached for a short period (30 seconds by default) using Laravel's configured cache store (Memcached in this project). M7 bypasses that cache and performs a fresh external-calendar recheck when a new hold/booking/reschedule is committed, closing the race where an outside calendar event appears after a client first viewed availability.

## Event synchronization

A synchronized appointment event is created as soon as the internal appointment/session exists, even if the booking is still awaiting email verification, contract review, staff confirmation, or payment. This reserves the staff calendar while the booking workflow completes.

Rescheduling updates/recreates the new appointment's external events. When the old appointment becomes orphaned, its external events are removed.

The scheduled reconciliation command is:

```
php artisan appointments:sync-calendars
```

Laravel Scheduler runs it every five minutes as a repair/reconciliation pass.

## Security

OAuth access and refresh tokens are stored using Laravel's encrypted Eloquent cast. Changing `APP_KEY` without a migration strategy makes those stored tokens unreadable.

Raw OAuth state is stored in the authenticated Laravel session and verified on callback.

M7 never puts OAuth tokens into browser-visible HTML after the callback.
