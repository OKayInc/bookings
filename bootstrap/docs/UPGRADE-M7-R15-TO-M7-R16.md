# Upgrade M7-R15 to M7-R16

M7-R16 is a source-only questionnaire pricing upgrade.

1. Back up the MariaDB database, application `.env`, and runtime `storage`.
2. Apply `m7-r15-to-m7-r16.patch` to an M7-R15 source tree, or replace the source with the M7-R16 full package while preserving `.env` and runtime `storage`.
3. Install the existing locked dependencies and clear cached configuration/routes/views:

```bash
composer install --no-dev --optimize-autoloader
php artisan optimize:clear
```

No migration command is required when upgrading from M7-R15. Do not run `migrate:fresh`. M7-R16 adds no table, column, index, or Composer dependency.

4. Review every address question using **Fee by distance range**. Add a positive **Distance per increment** and **Fee per increment**, even if the existing ranges appear continuous. Until saved, an uncovered legacy route fails closed rather than receiving a free charge.

5. Run focused tests followed by the full suite:

```bash
php artisan test --filter=DrivingDistancePricingServiceTest
php artisan test --filter=QuestionnaireConfigurationTest
php artisan test --filter=QuestionnaireBookingFlowTest
php artisan test --filter=DependentQuestionTest
php artisan test
```

6. Verification scenario:

   - Configure kilometers, a `$0` range from 0–10 km, a paid range from 20–30 km, and a fallback of `$10 per 5 km`.
   - Confirm a 7 km route uses the explicit free range.
   - Confirm a 12 km route falls in the gap and charges `$30` (`ceil(12 / 5) × $10`).
   - Confirm a 22 km route uses the configured 20–30 km range instead of the fallback.
   - Confirm the held-time quote and final booking total agree.
   - Inspect the booking price line and confirm it stores three fallback blocks without exposing point 0.
