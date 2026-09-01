# Milestones

- **M1 Foundation — complete:** authentication, persons, organizations, memberships, resources, UUID/MariaDB/Memcached/timezone foundation, basic appointment types and private/versioned contract templates.
- **M2 Appointment Type Configuration — complete:** access workflows, invite/password/unlisted links, capacity, fixed/variable duration rules with minute/hour/day/week increments, buffers, pricing, appointment logos, resource assignment, confirmation configuration and redirect configuration.
- **M3 Availability — complete:** schedules, inheritance/overrides, exceptions, slot generation, booking holds, MariaDB concurrency protection and resource availability.
- **M4 Booking — complete:** public Blade booking wizard, sessions, guest bookings, group capacity, attendees, timezone-aware client UI, email verification/passwordless management, contract download and signed-file upload/manual review.
- **M5 Questionnaire + Pricing — complete:** unlimited and reusable organization questions, chargeable checkbox/select/number/address answers, driving-distance and tiered short-notice fees, files, email/phone/address verification and deterministic price snapshots.
- **M6 Confirmation + Policies — complete:** employee/resource acceptance, cancellation/rescheduling policy enforcement and reminders.
- **M7 Calendars — complete:** Google Calendar and Microsoft Graph availability/synchronization with per-appointment-type calendar selection, followed by the M7 revision series through M7-R21.
- **M8 Ticketing — complete:** ticketed-event timing, optional seat numbering, one ticket per attendee, printable Code 128 tickets, lifecycle handling and organization-scoped admission check-in.
- **M9 Payments:** organization-specific Stripe/PayPal, retainers, balances, refunds, whitelist/blacklist rules.
- **M10 API:** two-key client/organization API authentication, organization-role authorization and administrative purge commands.
- **M11 Product plans:** free/paid capabilities, configurable free-plan caps, advertising and owner-granted forever-free unlimited accounts.

M8 adds ticketed events without changing the scheduling meaning of an appointment. The selected start is doors open, the appointment end remains the end of the resource-busy range, and show start/optional show end must fall inside that range. See `docs/TICKETING.md` and `docs/UPGRADE-M7-R21-TO-M8.md`.
