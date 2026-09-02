# Milestones

- **M1 Foundation — complete:** authentication, persons, organizations, memberships, resources, UUID/MariaDB/Memcached/timezone foundation, basic appointment types and private/versioned contract templates.
- **M2 Appointment Type Configuration — complete:** access workflows, invite/password/unlisted links, capacity, fixed/variable duration rules with minute/hour/day/week increments, buffers, pricing, appointment logos, resource assignment, confirmation configuration and redirect configuration.
- **M3 Availability — complete:** schedules, inheritance/overrides, exceptions, slot generation, booking holds, MariaDB concurrency protection and resource availability.
- **M4 Booking — complete:** public Blade booking wizard, sessions, guest bookings, group capacity, attendees, timezone-aware client UI, email verification/passwordless management, contract download and signed-file upload/manual review.
- **M5 Questionnaire + Pricing — complete:** unlimited and reusable organization questions, chargeable checkbox/select/number/address answers, driving-distance and tiered short-notice fees, files, email/phone/address verification and deterministic price snapshots.
- **M6 Confirmation + Policies — complete:** employee/resource acceptance, cancellation/rescheduling policy enforcement and reminders.
- **M7 Calendars — complete:** Google Calendar and Microsoft Graph availability/synchronization with per-appointment-type calendar selection, followed by the M7 revision series through M7-R21.
- **M8 Ticketing — complete through M8-R1:** ticketed-event timing, optional seat numbering, one ticket per attendee, printable Code 128 tickets, lifecycle handling, admission check-in, enforced ticket-compatible modes and paid seating-block fees.
- **M9 Payments — complete:** organization-specific Stripe/PayPal hosted checkout, full/retainer collection, client-paid balances, automatic/manual refunds and allowlist/blocklist rules.
- **M10 API:** two-key client/organization API authentication, organization-role authorization and administrative purge commands.
- **M11 Product plans:** free/paid capabilities, configurable free-plan caps, advertising and owner-granted forever-free unlimited accounts.

M9 makes payment a tenant-owned workflow: every organization supplies its own encrypted merchant credentials and every booking keeps immutable collection/refund terms. See `docs/PAYMENTS.md` and `docs/UPGRADE-M8-R1-TO-M9.md`.
