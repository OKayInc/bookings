# Member calendar defaults

## Set up a member

Open **Calendar connections**. Each member/resource has **Default calendar settings** followed by connected accounts and **Customize calendars by appointment type**.

Choose one **Default writing calendar** from the writable calendars across that member's Google and Microsoft connections. Selecting **Do not write appointments to an external calendar** disables default event writing. A writable shared calendar can be chosen explicitly as the writing target. No writing target is selected automatically.

By default, availability checks use every active calendar confirmed to be owned by the connected account. Calendars shared with that account are excluded even when they grant write or calendar-management access. Newly imported owned calendars are automatically included. The connected account's identity, not the backend user's login email or the manager performing the setup, determines ownership.

## Customize an appointment type

The lower section lists only appointment types linked to that member in the current organization. Each **Customize calendars** link opens settings scoped to that member. The summary shows the effective availability calendars, writing calendar, and whether member defaults or custom settings are in effect.

**Use member defaults** inherits automatic owned-calendar checks and the member's saved writing target. **Customize for this appointment type** allows any number of readable calendars for availability, including shared calendars, and at most one writable target for that member. No checked calendars and no writing target is a valid explicit override; it does not fall back to defaults. Selecting defaults again removes that member's custom choices for the type. Other members' settings are unchanged.

Read/write and read-only describe Appointment.To's use of a calendar; these settings do not grant or change provider sharing permissions. Employees can edit only their own linked member resources. Owners, administrators, and managers retain scheduling-management access within the active organization.

## Upgrade

After deploying this change, run:

```bash
php artisan migrate --force
php artisan optimize:clear
```

On existing connections, click **Refresh calendars** once to import ownership metadata for secondary Google and Microsoft calendars. A warning identifies calendars whose ownership is not confirmed. Such calendars are excluded from automatic checks and can be selected explicitly in appointment-type settings.

Existing `appointment_type_calendars` rows remain custom overrides and are not rewritten by the migration. Legacy configurations with no stored rows inherit defaults; the old schema could not distinguish an intentional empty choice from an unconfigured type. The new marker preserves explicit empty choices going forward. No new `.env` settings or frontend build are required. Follow your normal cache/OPcache deployment procedure on every HTTP node.

Defaults are read immediately for availability and new event syncs. Existing scheduled events are reconciled within the existing calendar-sync command's configured date range, including removal from an old writing target when provider access permits:

```bash
php artisan appointments:sync-calendars
```

Keep that command in your existing scheduled workflow. Default saves do not perform bulk provider HTTP calls during the page request. Custom appointment-type saves continue to synchronize affected upcoming scheduled appointments. A removed, revoked, or read-only target never causes an automatic switch to a different writing calendar.

## Implementation

`external_calendars.is_owned` is nullable: `true` means confirmed ownership, `false` means an identified different owner, and `null` means unknown. Google primary calendars belong to the authenticated account; secondary ownership uses `dataOwner`, not the ACL `owner` role. Microsoft uses `owner.address` against the connected account's mail/UPN identities, with the default-calendar flag as a fallback only when owner metadata is absent. Refresh preserves `is_default_write`.

`external_calendars.is_default_write` stores the selected target. Saving serializes on the resource row, clears choices only for the active organization/resource, and sets at most one target across both providers. Binary UUIDs and existing foreign keys are retained.

`appointment_type_resources.calendar_defaults_disabled` records explicit custom mode, including empty custom selections. Existing calendar pivot rows also imply custom mode for backward compatibility. `CalendarSelectionService` is the shared resolver for the page, availability, and event synchronization. It reads preference rows live rather than caching them in Redis or a loaded appointment graph. Busy-cache keys already include the effective calendar IDs. Only resources actually being checked or assigned are considered. Google free/busy requests are batched in groups of at most 50.

Provider references:
- Google CalendarList: https://developers.google.com/workspace/calendar/api/v3/reference/calendarList
- Google free/busy: https://developers.google.com/workspace/calendar/api/v3/reference/freebusy/query
- Microsoft calendar: https://learn.microsoft.com/en-us/graph/api/resources/calendar?view=graph-rest-1.0

## Regression checks

```bash
php artisan test --filter='CalendarDefaultsTest|CalendarOwnershipTest|CalendarConfigurationTest|CalendarBladeCompilationTest|CalendarSyncTest|ExternalCalendarAvailabilityTest|CalendarOauthStateTest|CalendarProviderTest'
```

The new cases cover ownership, shared editable calendars, saving a default repeatedly, switching providers, explicit no-write, custom/empty/reset overrides, employee and tenant isolation, free/busy failures, provider metadata refresh, event reassignment/update/cancellation, and Google free/busy batching.
