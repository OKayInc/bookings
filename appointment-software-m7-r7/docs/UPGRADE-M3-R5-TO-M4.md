# Upgrade M3-R5 → M4

M4 is additive. It keeps all existing users, organizations, contacts, resources, appointment types, availability, invitations and contract templates.

## 1. Back up

Back up the MariaDB database and application `.env` before upgrading.

## 2. Replace / patch source

Use the M3-R5 → M4 patch or replace the application source while preserving `.env`, `storage/`, and deployment-specific configuration.

## 3. Clear cached Laravel state

```bash
php artisan optimize:clear
```

## 4. Migrate

```bash
php artisan migrate
```

M4 adds migrations `000019` through `000025`:

- `appointments`
- `appointment_resources`
- M4 fields on `booking_holds`
- `bookings`
- `booking_attendees`
- `booking_contract_submissions`
- `booking_contract_files`

All new entity IDs use UUIDv7 stored as `BINARY(16)`.

## 5. Configure email

Development can keep:

```dotenv
MAIL_MAILER=log
```

For real bookings, configure SMTP or another Laravel mail transport. Guest email verification and passwordless management links require working outbound mail.

## 6. Scheduler

Make sure this cron exists:

```cron
* * * * * cd /path/to/appointment-software && php artisan schedule:run >> /dev/null 2>&1
```

M4 uses it to expire temporary holds and abandoned email-verification bookings.

## 7. Test

```bash
php artisan test
```

Use the dedicated MariaDB test database configured in M2-R1+.

## 8. Smoke test

1. Configure organization availability.
2. Open a public appointment URL in a logged-out browser.
3. Pick a timezone/date and load slots.
4. Reserve a slot.
5. Submit guest contact details.
6. If the appointment has a contract, download it and upload a signed copy.
7. Open **Bookings** in the backend.
8. Review the signed contract if present.
