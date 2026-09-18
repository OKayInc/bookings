# Upgrade M4-R2 to M4-R3

Back up the application/database, apply the R2 -> R3 source patch (or deploy the full R3 package), preserve your `.env` and `storage` content, then run:

```bash
php artisan optimize:clear
php artisan migrate
php artisan test
```

Do **not** run `migrate:fresh` on an installation containing data.

M4-R3 adds one migration:

```text
2026_08_24_000026_add_booking_notice_to_appointment_types.php
```

Existing appointment types receive:

```text
booking_notice_value = 0
booking_notice_unit = hour
```

which preserves their prior public booking behavior.
