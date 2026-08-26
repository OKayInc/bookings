# Upgrade M4-R3 to M4-R4

Apply the patch or replace the source while preserving `.env` and `storage`, then run:

```bash
php artisan optimize:clear
php artisan migrate
php artisan test
```

Do not run `migrate:fresh`.

Migration `2026_08_24_000027_add_maximum_booking_notice_to_appointment_types.php` adds the per-appointment maximum advance-booking window. Existing appointment types default to 365 days. Set the maximum value to `0` in the appointment-type editor for no maximum.
