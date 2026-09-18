# Upgrade M7-R14 to M7-R15

M7-R15 is an additive questionnaire schema and source upgrade.

1. Back up the MariaDB database, application `.env`, and runtime `storage`.
2. Apply `m7-r14-to-m7-r15.patch` to an M7-R14 source tree, or replace the source with the M7-R15 full package while preserving `.env` and runtime `storage`.
3. Install the existing locked dependencies, run the additive migration, and clear cached configuration/routes/views:

```bash
composer install --no-dev --optimize-autoloader
php artisan migrate
php artisan optimize:clear
```

Do not run `migrate:fresh` on an existing installation. The migration adds `appointment_question_visibility_conditions`; it does not modify or backfill existing questions, answers, or bookings. Existing questionnaires remain fully visible because they have no condition rows.

4. Run focused tests followed by the full suite:

```bash
php artisan test --filter=DependentQuestionTest
php artisan test --filter=QuestionnaireConfigurationTest
php artisan test --filter=QuestionnaireBookingFlowTest
php artisan test --filter=QuestionnairePricingServiceTest
php artisan test --filter=ReusableQuestionTest
php artisan test
```

5. Verification scenario:

   - Create radio question 1 with answers A and C.
   - Create checkbox question 2 with answer B.
   - Create required question 9 after both and add `Q1=A AND Q2=B OR Q1=C` in **Display dependencies**.
   - Confirm question 9 is hidden for A alone, visible for A+B, and visible for C.
   - Configure a price on question 9 and confirm its answer/fee disappears from the live quote when the expression becomes false.
   - Submit a direct request containing a hidden question 9 value and confirm the booking has no question 9 answer or price line.
   - Edit answer A's label and confirm the dependency remains; attempt to remove A and confirm the editor blocks the change until the dependency is removed.
