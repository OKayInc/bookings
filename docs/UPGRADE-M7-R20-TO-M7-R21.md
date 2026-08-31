# Upgrade M7-R20 to M7-R21

1. Back up MariaDB, `.env`, `APP_KEY`, and runtime `storage`.
2. Apply `m7-r20-to-m7-r21.patch` at the M7-R20 application root, or deploy the full M7-R21 source ZIP while preserving configuration and runtime files. Include the updated `public/js/numeric-question-constraints.js`.
3. Run the additive migration and clear caches:

```bash
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan optimize:clear
```

Do not run `migrate:fresh` on an existing installation. No new Composer dependencies or environment variables are required. Existing constraints do not need to be recreated.

## Configure

Open **Appointment type → Questionnaire → Edit numeric question → Numeric answer constraints**. Add a rule, choose the comparison operator, and select **Number of attendees** under **Compare with**. No question or fixed value should be entered for this operand.

The count includes the primary client and applies only to this booking. AND/OR conditions work as before.

## Verification on a dedicated test host

Install development dependencies with `composer install`, and configure a separate MariaDB testing database whose name contains `test`. Never point the suite at production.

```bash
php artisan test --filter=NumericComparisonTest
php artisan test --filter=NumericQuestionConstraintTest
php artisan test --filter=QuestionnaireBookingFlowTest
php artisan test --filter=DependentQuestionTest
php artisan test --filter=ReusableQuestionTest
php artisan test --filter=PerAttendeePricingTest
php artisan test --filter=BladeCompilationTest
php artisan test
node --test tests/JavaScript/*.test.cjs
```

Manual checks:

- Configure `Meals needed <= Number of attendees`. Reserve 3 seats: answer 3 must pass and 4 must fail, in both the browser and a direct POST.
- Combine `answer >= Q1 AND answer <= Number of attendees OR answer = 0`; verify both OR groups and the preview.
- Save/edit the rule and switch among all three operand options. In attendee mode, neither the question selector nor fixed-number input should submit stale values.
- Have two clients reserve different seat counts in the same session. Each comparison must use its own booking's count, not total session attendance or capacity.
- Check single attendance (count 1), optional and hidden questions, and desktop/mobile layouts.

## Rollback

M7-R20 cannot interpret attendee-count rules. Before reverting code or rolling back the new migration, stop bookings and deliberately remove or replace each affected rule, reviewing its AND/OR expression. Do not simply drop the column while attendee-count rows remain: they would become invalid legacy fixed-number rules. Rolling back removes the operand discriminator, not questions or booking history. Keep the backup if the rules must be restored later, and clear caches after reverting.
