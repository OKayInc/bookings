# Upgrade M7-R10 to M7-R11

M7-R11 is an additive pricing upgrade. Existing appointment types have no short-notice fees until an authorized manager explicitly configures them.

1. Back up the MariaDB database, application `.env`, and runtime `storage`.
2. Apply `m7-r10-to-m7-r11.patch` to an M7-R10 source tree, or replace the source with the M7-R11 full package while preserving `.env` and runtime `storage`.
3. Install the existing locked dependencies and run the additive migration:

```bash
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan optimize:clear
```

Do not run `migrate:fresh`. The migration only creates `short_notice_fee_rules`; it does not modify existing appointment types, bookings, answers, or price snapshots. M7-R11 has no new Composer package.

4. Run focused tests followed by the complete suite:

```bash
php artisan test --filter=ShortNoticeFeeTest
php artisan test --filter=QuestionnairePricingServiceTest
php artisan test --filter=QuestionnaireBookingFlowTest
php artisan test
```

5. Verification scenario:

   - Edit a paid appointment type and add 72-hour 10%, 24-hour 25%, and 6-hour fixed fee tiers.
   - Select a start within six hours and confirm only the fixed tier appears in the held-time quote.
   - Select a start between six and 24 hours and confirm only the 25% tier appears.
   - Add a priced questionnaire answer and confirm the percentage is calculated from the subtotal including that extra.
   - Complete a booking and confirm its total and price-line breakdown retain the fee after the appointment type's rules are later edited.
