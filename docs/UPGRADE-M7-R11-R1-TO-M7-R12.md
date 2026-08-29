# Upgrade M7-R11-R1 to M7-R12

M7-R12 is a source-only questionnaire pricing upgrade. Existing questions and bookings are unchanged until an authorized manager enables driving-distance pricing on an address question.

1. Back up the MariaDB database, application `.env`, and runtime `storage`.
2. Apply `m7-r11-r1-to-m7-r12.patch` to an M7-R11-R1 source tree, or replace the source with the M7-R12 full package while preserving `.env` and runtime `storage`.
3. Enable both **Address Validation API** and **Routes API** in the Google Cloud project used by the application.
4. Configure a server-side key. A dedicated restricted key is recommended; when omitted, M7-R12 falls back to the existing key:

```dotenv
GOOGLE_MAPS_API_KEY=address-validation-key
GOOGLE_ROUTES_API_KEY=routes-api-key
GOOGLE_ROUTES_CACHE_SECONDS=900
```

5. Install the existing locked dependencies and clear cached configuration/views:

```bash
composer install --no-dev --optimize-autoloader
php artisan optimize:clear
```

No migration command is required when upgrading from M7-R11-R1. Do not run `migrate:fresh`. M7-R12 adds no table, column, or Composer dependency.

6. Run focused tests followed by the full suite:

```bash
php artisan test --filter=DrivingDistance
php artisan test --filter=QuestionnaireConfigurationTest
php artisan test --filter=QuestionnaireBookingFlowTest
php artisan test
```

7. Verification scenario:

   - Create an address question with a point 0 that is recognizable but safe for testing.
   - Confirm the public hold page does not contain point 0.
   - Configure a free 0–10 km range, a charged 10–25 km range, and an open-ended 25+ km range.
   - Enter a routable client address and confirm exactly one matching distance fee appears in the held-time quote.
   - Complete the booking and confirm the answer snapshot contains route meters/unit while the price line contains the selected range and neither contains point 0.
   - Temporarily disable the Routes API or use an unroutable destination and confirm the booking fails with a validation error rather than omitting the fee.
