# Upgrade M5-R7 to M6

M6 is an additive upgrade. Preserve your existing `.env`, `storage/` data, and MariaDB database.

## 1. Apply the source/patch

Replace application source files or apply `m5-r7-to-m6.patch` from the project root.

## 2. Clear Laravel caches

```bash
php artisan optimize:clear
```

## 3. Run migrations

```bash
php artisan migrate
```

M6 adds:

- `2026_08_25_000035_create_resource_confirmations_table.php`
- `2026_08_25_000036_add_policies_and_reminders_to_appointment_types.php`
- `2026_08_25_000037_add_policy_snapshots_to_bookings.php`
- `2026_08_25_000038_create_reminder_deliveries_table.php`
- `2026_08_25_000039_create_booking_reschedules_table.php`

No M5 table is dropped or recreated. Migration `000037` also snapshots the current appointment-type staff-confirmation requirement onto existing bookings.

## 4. Initialize existing pending staff bookings

M5 could already leave bookings in `pending_staff_confirmation`, but it did not have per-resource confirmation records. Run once after the upgrade:

```bash
php artisan appointments:sync-staff-confirmations
```

The command is idempotent and is also scheduled every ten minutes, so missed records are repaired automatically.

If production email is enabled, this command sends confirmation requests to eligible person-resources.

## 5. Verify Scheduler

Keep the standard scheduler cron active:

```cron
* * * * * cd /path/to/appointment-software && php artisan schedule:run >> /dev/null 2>&1
```

M6 schedules:

- expired booking-hold cleanup
- abandoned pending-booking cleanup
- staff-confirmation synchronization
- due appointment reminders

## 6. Run tests

```bash
php artisan test
```

Useful focused tests:

```bash
php artisan test --filter=StaffConfirmationTest
php artisan test --filter=BookingPolicyTest
php artisan test --filter=BookingReminderTest
```

Do not run `migrate:fresh` against your real application database.
