# Architecture — M3

## Modular monolith

The application remains a Laravel modular monolith with Blade/vanilla JavaScript. M3 keeps the modular-monolith structure and adds `app/Domain/Availability` for schedule resolution, slot generation, duration arithmetic, and transactional temporary holds. Controllers remain orchestration layers.

## Tenant boundary

A `User` authenticates one `Person`; a person can belong to many organizations. Organization-owned data carries `organization_id`. `EnsureActiveOrganization`, `OrganizationContext`, explicit tenant-scoped queries and authorization policies remain the tenant-safety layers.

## UUIDs

Entity identifiers remain UUIDv7 stored as MariaDB `BINARY(16)`. Public URLs use normal canonical UUID strings only where model binding is needed; appointment access tokens are independent random secrets and are not entity identifiers.

## Appointment access

M2 distinguishes discovery from authorization:

- **Public:** listed and directly accessible by organization + slug.
- **Unlisted:** omitted from listings and accessible only with its random secret URL token.
- **Password protected:** omitted from listings; direct slug URL shows a password gate. Passwords remain one-way hashes and the unlock endpoint is throttled.
- **Invite only:** omitted from listings. Each invitation has an independent random token whose SHA-256 hash is stored. Invitations can expire, be revoked and optionally be tied to an email / future booking-count limit.

Opening an invitation does not consume it. Booking use is a future transactional operation in M4.

## Appointment type pricing

M3 retains M2 pricing configuration and still does not introduce payment state. `AppointmentTypePricingService` computes free/fixed/rate totals with integer arithmetic. The duration unit and rate unit are independent; for example a client may select 30-minute increments while pricing is CAD 150/hour.

Actual booking price snapshots, questionnaire surcharges, retainers and gateway payments arrive later.

## Public files vs private files

- Appointment logos are public presentation assets on Laravel's `public` disk.
- Contract templates remain private and versioned on the private `local` disk.
- Future signed contract submissions remain private.

SVG logos are intentionally excluded in M2 so unsanitized active SVG content is never served directly.

## Duration and timezone boundary

M3 converts selected civil durations into UTC appointment instants. Minute/hour/day/week duration arithmetic is performed in the selected booking timezone before conversion back to UTC, preserving DST-aware calendar-day/week semantics. MariaDB timezone tables remain a required deployment dependency for SQL-side `CONVERT_TZ()` operations.

## Memcached boundary

Memcached remains replaceable cache only. Invite tokens, appointment configuration, booking state, temporary holds, and concurrency state are not authoritative in Memcached. Durable state and pessimistic locking live in MariaDB.
