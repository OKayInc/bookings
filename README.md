# Appointment Software — M9-R1

M9-R1 adds quantity-aware equipment inventory and free, per-piece, exact-bundle or fixed rental pricing to M9's organization-owned payment workflow. See `docs/EQUIPMENT-INVENTORY.md`, `docs/CHANGES-M9-R1.md`, and `docs/UPGRADE-M9-TO-M9-R1.md`.

## M9-R1 equipment capabilities

- Explicit quantity tracking and physical stock counts for interchangeable equipment, including shared resources.
- Guarded deletion for resources that have never entered a booking hold or appointment.
- Explicit numeric-question **Answer × rate** add-ons, including decimal answers and included units.
- Per-appointment-type required quantities snapshotted onto holds and appointments.
- Overlapping availability based on remaining pieces rather than binary busy/free state.
- Public slot stock such as “19 of 20 available” plus the quantity the slot will reserve.
- Free, per-piece, fixed-fee and cheapest-exact-bundle pricing.
- Equipment price lines included in M9 checkout, retainers, balances and refunds.

## M9 payment capabilities

- Encrypted Stripe and PayPal credentials per organization; no platform-wide merchant account.
- Stripe-hosted Checkout and PayPal Orders v2 capture without card storage.
- Full-price or fixed/percentage retainer collection from the private booking page.
- Remaining-balance payment with an organization-local due-date snapshot.
- Exact amount/currency reconciliation, verified webhooks and provider idempotency keys.
- Configurable client/staff cancellation refund percentages plus staff manual refunds.
- Safe refund retries, duplicate-capture overpayment protection and late-cancellation refunds.
- Exact-email or domain allowlists and blocklists, with blocklist precedence.

## M7 calendar synchronization

M7 adds Google Calendar and Microsoft Outlook/Microsoft 365 connections per resource, appointment-type calendar selection for availability, and outbound appointment event synchronization. See `docs/CALENDAR-INTEGRATIONS.md` and `docs/UPGRADE-M6-R2-R3-TO-M7.md`.

## M6-R2-R3 baseline

M6-R2 builds on M6-R1 and adds staff-initiated schedule-change proposals to the operational staff confirmation, policy, reminder and guest-booking workflows while preserving the Laravel + Blade modular-monolith architecture.

## Stack

- PHP 8.3+
- Laravel 13
- Blade + vanilla JavaScript only
- MariaDB using Laravel's `mariadb` driver
- Memcached
- UUIDv7 application identifiers stored as `BINARY(16)`
- MariaDB timezone tables loaded with `mariadb-tzinfo-to-sql`
- `giggsey/libphonenumber-for-php-lite` from M5
- Google Address Validation API for address questionnaire questions

## M6 capabilities

Everything from M5-R7, plus:

- Booking-snapshotted staff-confirmation requirement plus per-resource confirmation records
- Required person-resources block confirmation until accepted
- Optional person-resources may accept/decline without blocking the booking
- Staff Accept / Decline from the authenticated backend
- Private no-login staff response links delivered by email
- Optional staff response notes
- Managers can send a fresh confirmation reminder link
- Client notification after all required staff accept
- Required staff decline moves the booking to `declined`
- Client cancellation policies snapshotted onto each booking
- Client rescheduling policies snapshotted onto each booking
- Configurable cancellation/rescheduling notice in minutes, hours, days, weeks or months
- Configurable maximum reschedule count (`0` = unlimited)
- Passwordless client cancellation from the booking-management page
- Passwordless client rescheduling using live availability and MariaDB booking holds
- Rescheduling resets staff approvals for the new time
- Reschedule history preserved in `booking_reschedules`
- Staff administrative cancellation override
- Configurable appointment reminders for clients and resources
- Reminder threshold can be based on booking lead time or appointment duration
- Reminder timing supports minutes, hours, days, weeks and calendar months
- Multi-attendee reminders go to the primary client plus attendees with unique email addresses
- Resource reminders are de-duplicated per appointment session/resource


## M6-R2 schedule-change proposals

Staff/managers can propose a different appointment time without immediately changing the booking. The alternative slot is reserved while the client decides. The client can:

- **Accept proposed time** — moves the booking and resets required staff confirmation for the new time.
- **Keep original time** — leaves the original booking in place with an active staff-availability warning.
- **Cancel booking** — records the cancellation as caused by a staff schedule issue so the snapshotted staff refund percentage is applied.

Proposal acceptance does not consume the client's reschedule quota. Proposal creation may bypass normal new-booking advance-notice limits, but it still enforces real resource availability, buffers, capacity and concurrency locks.

See [`docs/SCHEDULE-CHANGE-PROPOSALS.md`](docs/SCHEDULE-CHANGE-PROPOSALS.md).

## Upgrade from M5-R7

See [`docs/UPGRADE-M5-R7-TO-M6.md`](docs/UPGRADE-M5-R7-TO-M6.md).

Typical upgrade:

```bash
php artisan optimize:clear
php artisan migrate
php artisan appointments:sync-staff-confirmations
php artisan test
```

Do **not** run `migrate:fresh` on an existing installation.

Laravel Scheduler must continue running every minute. M6 adds reminder delivery and staff-confirmation synchronization to the existing scheduled tasks.

## Staff confirmation semantics

Whether confirmation is required is snapshotted onto each booking. Confirmations are generated from the resource snapshot attached to the actual appointment/session. Required person-resources must accept. Optional person-resources can respond, but their pending/declined state does not prevent a client booking from progressing.

A booking can therefore show:

```text
Photographer       Required  Accepted
Assistant          Optional  Declined
Studio             Required  (non-person resource, no response required)
```

Only person-resources receive confirmation emails.

## Policies

Cancellation and rescheduling policies are copied from the appointment type into the booking when it is created. Later changes to the appointment type do not retroactively modify an existing client's policy.

M9 connects cancellation to the snapshotted client/staff refund percentage. M6 continues to enforce whether and when the booking may be cancelled or rescheduled.

## Reminders

Run manually if desired:

```bash
php artisan appointments:send-reminders
```

The scheduled command is idempotent through `reminder_deliveries`, so the same client/resource reminder is not sent repeatedly.

## M6 Revision 1 UI

M6-R1 uses Bootstrap 5.3.8 for the backend and public Blade layouts. The backend navigation is responsive and groups scheduling/organization functions into dropdowns so the top bar remains usable as more features are added. See `docs/BOOTSTRAP-UI.md`.


## Upgrade M6-R1 → M6-R2

See [`docs/UPGRADE-M6-R1-TO-M6-R2.md`](docs/UPGRADE-M6-R1-TO-M6-R2.md). This is an additive database upgrade; do not run `migrate:fresh`.


## M7-R1: backend email verification and Mailgun

Backend registrations now require email verification before administration access. Mailgun can be selected as the application mailer using its HTTP API (`MAIL_MAILER=mailgun`) rather than SMTP. See `docs/MAIL-AND-EMAIL-VERIFICATION.md` and `docs/UPGRADE-M7-TO-M7-R1.md`.


## M7-R2: durable calendar OAuth state

Calendar OAuth no longer depends on the Laravel session surviving the Google/Microsoft consent round trip. Short-lived, one-time hashed OAuth transactions are stored in MariaDB. This revision is based on the current GitHub repository and preserves the manual Apache `.htaccess` and session-independent backend email-verification fix. See `docs/UPGRADE-REPOSITORY-TO-M7-R2.md`.

## M7-R3: organization logos

Organizations can now upload a default logo. It is displayed in backend/public navigation and is used as the fallback image for appointment types that do not define their own logo. See `docs/CHANGES-M7-R3.md`.

## M7-R3-R1

Test-only fix for organization logo fallback coverage. See `docs/CHANGES-M7-R3-R1.md`.

## M7-R4-R1

MariaDB migration recovery fix for shared resources. The M7-R4 migration now preserves a dedicated `resource_id` index for the calendar connection foreign key and safely resumes after partial DDL. See `docs/CHANGES-M7-R4-R1.md`.

## M7-R4-R3: durable active organization

Organization switching now persists the selected organization on the backend user, with session state retained as a compatibility/cache layer. This prevents a successful switch POST from reverting to the first membership when the next request loses or carries stale session state.

## M7-R5: organization-member invitations and resource booking email

Owners and administrators can invite backend members without making them organization owners. New invitees create a person/user account through the email-bound invitation, while existing backend users log in and join with their existing account. A person linked to an assigned resource now receives an immediate email when a booking involving that resource is created. See `docs/CHANGES-M7-R5.md` and `docs/UPGRADE-M7-R4-R3-TO-M7-R5.md`.

## M7-R6: holiday closures and fast organization switching

Managers, administrators, and owners can opt an organization into fixed-date, Easter-relative, nth-weekday, or one-time holiday closures. Active closures are organization-wide hard blocks in the organization's IANA timezone and are enforced again during booking-hold acquisition. No holiday is enabled automatically. Users with more than one active organization membership can switch organizations from a responsive navbar dropdown. See `docs/CHANGES-M7-R6.md` and `docs/UPGRADE-M7-R5-TO-M7-R6.md`.

## M7-R7: international and resource holiday calendars

The holiday picker now suggests a country or subdivision from the organization timezone and supports explicit international region selection. Selected or date-equivalent holidays disappear from the picker and cannot be created twice. Each organization-resource link may also enforce all official/bank holidays for its own region, so required resources in different countries contribute the union of their closed days while unavailable optional resources are skipped. See `docs/CHANGES-M7-R7.md` and `docs/UPGRADE-M7-R6-TO-M7-R7.md`.

## M7-R8: cross-organization members as person resources

A backend user may own one organization while accepting an employee, manager, or administrator invitation to another. After acceptance, the receiving organization can select that person's backend account email when creating a person resource or use the direct **Create person resource** action from the member list. Pending invitations are visible but cannot be linked until accepted, and unrelated organization owners remain isolated. See `docs/CHANGES-M7-R8.md` and `docs/UPGRADE-M7-R7-TO-M7-R8.md`.

## M7-R9: replacement resource groups

Appointment types can now require one resource from a named replacement group, such as **Photographer A or Photographer B**. Availability remains open while at least one group member is available, holds snapshot all currently available candidates, and staff confirmation succeeds when any candidate accepts. The accepted candidate is retained on the appointment while the other candidates are marked **Not needed** and released for other bookings. Schedules, busy periods, external calendars, and per-resource regional holidays are evaluated independently for every candidate. See `docs/CHANGES-M7-R9.md` and `docs/UPGRADE-M7-R8-TO-M7-R9.md`.

## M7-R10: reusable questionnaire questions

Question definitions can now be reused across appointment types in the same organization. Before creating a question, the questionnaire builder lists and searches the organization's existing reusable questions and shows which ones are already attached. Attaching copies the full definition, choices, validation, and pricing into an independent appointment-type question, while creating a new question automatically adds it to the reusable library. Existing questions are backfilled without changing booking-answer history. See `docs/CHANGES-M7-R10.md` and `docs/UPGRADE-M7-R9-TO-M7-R10.md`.

## M7-R11: tiered short-notice fees

Appointment types can now add fixed or percentage fees based on how soon the selected appointment will start. Multiple thresholds support progressively higher fees as notice becomes shorter; only the shortest matching threshold applies. Percentage fees use the subtotal after questionnaire extras, and the selected rule is included in the live guest quote and immutable booking price lines. See `docs/CHANGES-M7-R11.md` and `docs/UPGRADE-M7-R10-TO-M7-R11.md`.

## M7-R11-R1: Laravel 13 Blade form fix

The appointment-type editor now uses explicit Blade PHP blocks for its short-notice fee variables, preventing the Laravel 13 compiled-view parse error reported by `AppointmentTypeConfigurationTest`. Regression coverage renders both an appointment type with no fee tiers and one with fixed and percentage tiers. See `docs/CHANGES-M7-R11-R1.md` and `docs/UPGRADE-M7-R11-TO-M7-R11-R1.md`.

## M7-R12: address driving-distance fees

Reusable address questions can now define a private point 0 and optionally add either one fixed driving-distance fee or non-overlapping kilometer/mile range fees. The server requests only `distanceMeters` from Google Routes, includes the result in the held-time quote, validates it again during final submission, and snapshots the distance and selected rate without exposing the configured origin. See `docs/CHANGES-M7-R12.md` and `docs/UPGRADE-M7-R11-R1-TO-M7-R12.md`.

## M7-R13: permanent organization deletion

Active owners now have a guarded danger zone for permanently deleting an organization. Exact-name and current-password confirmation protects the action; organization-owned data and stored files are removed, incoming shared resources are detached and preserved, and owned resources are unshared everywhere before deletion. The user's active organization then moves to another active membership or the organization-creation page. See `docs/CHANGES-M7-R13.md` and `docs/UPGRADE-M7-R12-TO-M7-R13.md`.

## M7-R14: cross-midnight slots and cleaner time boxes

The public date picker now shows time-only slot boxes instead of repeating the selected date. Availability treats the requested day as the allowed start-date range rather than a mandatory finish boundary, so a long appointment may consume continuous available hours after midnight while next-day schedules, conflicts, resources, holidays, buffers, and external calendars remain enforced. See `docs/CHANGES-M7-R14.md` and `docs/UPGRADE-M7-R13-TO-M7-R14.md`.

## M7-R15: dependent questionnaire questions

Appointment-type questionnaires can now show a question only when earlier checkbox, radio, or select answers match a Boolean expression. A checkbox condition may accept several possible answers and matches when any configured answer is selected. AND conditions form a group and OR starts an alternative group, supporting rules such as `(Question 1 = A AND Question 2 = B) OR Question 1 = C`. The browser updates immediately, while the server independently excludes hidden questions from required validation, provider verification, file handling, pricing, and booking-answer snapshots. See `docs/QUESTIONNAIRES.md`.

## M7-R16: per-distance fallback fees

Driving-distance range pricing now requires a positive fallback fee expressed as an amount per configurable kilometer/mile increment. A configured range still wins when it matches, including an intentional zero-dollar free radius; otherwise every started fallback increment is charged. Legacy uncovered ranges without a fallback fail closed instead of silently producing a free route. See `docs/CHANGES-M7-R16.md` and `docs/UPGRADE-M7-R15-TO-M7-R16.md`.

## M7-R16-R1: distance increment persistence assertion

The questionnaire configuration regression test now normalizes the JSON-backed fallback increment to a float before its strict assertion. This accommodates MariaDB returning the whole-number value `5` after a submitted `5.0` is persisted, without weakening the expected numeric value or changing runtime pricing behavior. See `docs/CHANGES-M7-R16-R1.md` and `docs/UPGRADE-M7-R16-TO-M7-R16-R1.md`.

## M7-R17: organization conference providers

Organization settings now store encrypted Google questionnaire API keys plus Google Meet, Microsoft Teams, Zoom, Webex, and custom-link credentials. Appointment types can be marked online and select any configured provider; Jitsi is always available without credentials. New appointments snapshot the provider and provision a private join link, with staff-visible failure details and retry support. See `docs/CHANGES-M7-R17.md`, `docs/CONFERENCE-INTEGRATIONS.md`, and `docs/UPGRADE-M7-R16-R1-TO-M7-R17.md`.

## M7-R18: recurring booking seasons

Appointment types can now be restricted to an inclusive one-time or yearly recurring date window in the organization's timezone, including seasons that cross New Year. Off-season types are hidden from the public organization catalog, complete appointments must fit inside the season, and holds/bookings/reschedules are revalidated server-side. Existing appointment types remain year-round. See `docs/CHANGES-M7-R18.md` and `docs/UPGRADE-M7-R17-TO-M7-R18.md`.

## M7-R19: group per-attendee pricing

Group appointment types can charge a flat rate per attendee, use absolute ranges (the matching unit price applies to every attendee), or accumulative ranges (each portion uses its own unit price). Each booking is priced only for its own attendees. Public previews, checkout quotes, and saved booking price lines use the authoritative held attendee count. See `docs/CHANGES-M7-R19.md` and `docs/UPGRADE-M7-R18-TO-M7-R19.md`.

## M7-R20: numeric answer constraints

Numeric questionnaire answers can be compared with an earlier numeric answer or a fixed number using `>`, `>=`, `=`, `<=`, `<`, or different-from (`!=`, `<>`, `!`). Ordered AND/OR rules include a grouped editor preview, live form feedback, and authoritative quote/booking validation. Rules belong to the appointment-type attachment, independently of display dependencies and reusable templates. See `docs/CHANGES-M7-R20.md` and `docs/UPGRADE-M7-R19-TO-M7-R20.md`.

## M7-R21: attendee-count numeric constraints

The numeric constraint editor now offers **Number of attendees** as a comparison operand. It uses the seats reserved for the individual booking, including the primary client, with all existing operators and AND/OR rules. Browser feedback, live quotes, and final booking validation use the held count; other clients' seats and submitted replacement counts do not affect it. See `docs/CHANGES-M7-R21.md` and `docs/UPGRADE-M7-R20-TO-M7-R21.md`.

## M8 and M8-R1: ticketed events

Appointment types can issue one ticket per attendee. Ticketed events force group attendance, fixed duration, and free or per-attendee pricing. The selected appointment start is displayed as **doors open**, show start and optional show end are constrained inside the resource-busy booking range, and those times are snapshotted on the shared event session. Seating may be unassigned, consecutive, section + seat, row + seat, or section + row + seat; section/row schemes can intentionally omit the seat component, use a configured quantity, and add a per-ticket seating fee for paid events. Seats and fees are held through checkout. Tickets receive unique printable Code 128 barcodes, remain reserved until the booking is confirmed, are voided on cancellation/decline, retain their historical printed seat, and can be admitted once from the organization ticket check-in desk. See `docs/TICKETING.md`, `docs/CHANGES-M8.md`, `docs/CHANGES-M8-R1.md`, and `docs/UPGRADE-M8-TO-M8-R1.md`.

## M9: organization-owned payments

Payment terms are configured on each appointment type and copied to the booking after the final price is calculated. A successful full payment or retainer satisfies `pending_payment`; any remaining balance stays visible and payable from the passwordless management page. Provider events are tenant-scoped, signature-verified and deduplicated. Refunds return through the original capture and are serialized per booking to prevent double refunds. See `docs/PAYMENTS.md`.
