# Upgrade M6-R1 to M6-R2

M6-R2 is an additive upgrade. Do not use `migrate:fresh` on an installed system.

## 1. Apply the M6-R1 → M6-R2 patch or replace the source tree

Preserve your existing `.env`, `storage` data and application-specific deployment configuration.

## 2. Optional proposal timing configuration

Defaults are already provided. Add these only if you want different values:

```dotenv
BOOKING_SCHEDULE_PROPOSAL_DEFAULT_TTL_HOURS=24
BOOKING_SCHEDULE_PROPOSAL_MAX_TTL_HOURS=168
```

## 3. Clear cached application configuration

```bash
php artisan optimize:clear
```

## 4. Run the two additive migrations

```bash
php artisan migrate
```

M6-R2 adds:

- `2026_08_25_000040_create_booking_schedule_proposals_table.php`
- `2026_08_25_000041_add_cancellation_origin_to_bookings.php`

Existing bookings, appointments, confirmations, policies, questionnaires and payment-state placeholders are preserved.

## 5. Run tests

```bash
php artisan test
```

For the new feature specifically:

```bash
php artisan test --filter=StaffScheduleProposalTest
```

## 6. Keep Laravel Scheduler running

The existing cron remains sufficient:

```cron
* * * * * cd /path/to/appointment-software && php artisan schedule:run >> /dev/null 2>&1
```

M6-R2 adds the scheduled command:

```bash
php artisan appointments:expire-schedule-proposals
```

It is registered to run every ten minutes. Booking-hold expiration continues to run every minute.
