
## M7 calendar synchronization

M7 adds Google Calendar and Microsoft Outlook/Microsoft 365 connections per resource, appointment-type calendar selection for availability, and outbound appointment event synchronization. See `docs/CALENDAR-INTEGRATIONS.md` and `docs/UPGRADE-M6-R2-R3-TO-M7.md`.

# Appointment Software — M7

# Appointment Software — M6-R2-R3

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
- **Cancel booking** — records the cancellation as caused by a staff schedule issue so M8 can apply the appropriate refund logic.

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

M8 will connect cancellation/payment/refund consequences. M6 enforces whether and when the booking may be cancelled or rescheduled.

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
