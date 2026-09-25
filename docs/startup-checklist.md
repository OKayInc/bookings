# Organization startup checklist

Owners, administrators and managers see the active organization's checklist above upcoming bookings on the dashboard. The **Organization → Startup checklist** menu returns to it from any setup page.

The checklist works for existing organizations and for blank, guided or template-created configurations. It reads current settings each time; there are no manually maintained completion flags, new database columns or setup-path dependencies. Removing or disabling a required setting makes its check incomplete again. Switching organizations switches the checklist.

## Suggested order

1. Review the business name, booking address, timezone and currency.
2. Add people, rooms, vehicles or equipment if the business needs resources.
3. Set business hours or custom appointment-type hours.
4. Create and enable appointment types, including duration, price, capacity and access settings.
5. Check assigned resources, their hours, replacement groups and inventory quantities.
6. For ticketed events, configure future dates and in-person locations. For online appointments, configure the selected meeting provider.
7. Configure tax rates if tax collection is enabled.
8. Configure either Stripe or PayPal in live mode if appointments can charge money.
9. Preview available times and the customer booking page before sharing links.

Every row has a check, cross or “Not needed now” status plus a link to the relevant page. Only essential, applicable checks count toward progress. The next unfinished essential step is highlighted. Completed organizations retain a collapsed checklist so it remains available for review.

## How checks work

- Availability follows the booking engine's inheritance: custom schedules override organization hours, including when the custom schedule is disabled or empty. Active weekly rules or a future opening exception count as configured hours.
- Required resources must be active, linked to this organization, have effective hours and sufficient configured inventory. Each replacement group needs at least one usable candidate. Optional resources and resource-free appointments do not block the checklist.
- All active appointment types are checked. Disabled types are ignored. Public, unlisted, password-protected and invite-only types all count; the checklist does not force private services to become public.
- Payment checks include prices, question/option charges, distance pricing, short-notice fees, equipment pricing, seat fees and refundable deposits, including conditional rental deposits. One complete live provider is enough; both are not required. Credentials and webhook settings must be present. Stripe test keys and test/sandbox modes do not satisfy the live-payment check.
- Meeting setup uses the existing conference provider catalog. Jitsi requires no separate credentials. A delayed event-location disclosure does not make the configured location incomplete.
- Branding, booking questions, policy text, external calendars and team invitations are optional finishing touches.
- Managers can follow scheduling links. Organization, tax, payment, branding and team administration steps direct them to an owner or administrator. Employees do not receive configuration details or checklist links.

This is a configuration checklist, not a promise that a particular slot can be booked. Calendar conflicts, overlapping hours, holiday closures, booking notice, provider connectivity, email delivery and the complete customer checkout still need to be checked through the preview or a trial booking. Private links and invitations remain available through each appointment type's editor.

## Implementation and verification

`app/Domain/Organizations/OrganizationStartupChecklist.php` builds the checklist from organization-scoped records. `DashboardController` only builds it for members allowed to manage scheduling. Blade partials render the checklist using Bootstrap and native expandable sections; no JavaScript build is needed.

Run the focused tests against the dedicated MariaDB test database:

```bash
php artisan test --filter='OrganizationStartupChecklistTest|M9R5DashboardTest|GuidedOrganizationSetupTest|FirstRunOnboardingTest|CurrentOrganizationNavigationTest'
```

No database migration or new environment variable is required.
