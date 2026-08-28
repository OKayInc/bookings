# Upgrade M7-R9 to M7-R10

M7-R10 is an additive questionnaire upgrade. It preserves users, organizations, memberships, resources, appointment types, schedules, bookings, questionnaire answers, uploaded files, price snapshots, confirmations, calendars, and holidays.

1. Back up the MariaDB database, application `.env`, and runtime `storage`.
2. Apply `m7-r9-to-m7-r10.patch` to an M7-R9 source tree, or replace the source with the M7-R10 full package while preserving `.env` and runtime `storage`.
3. Install the existing locked dependencies and run the additive migration:

```bash
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan optimize:clear
```

Do not run `migrate:fresh`. The migration creates one reusable template for every existing appointment question and copies its options. Existing appointment questions and booking answers are not replaced. M7-R10 has no new Composer package.

4. Run focused tests followed by the complete suite:

```bash
php artisan test --filter=ReusableQuestionTest
php artisan test --filter=QuestionnaireConfigurationTest
php artisan test --filter=QuestionnaireBookingFlowTest
php artisan test --filter=QuestionnairePricingServiceTest
php artisan test
```

5. Verification scenario:

   - Open the questionnaire for an appointment type that already has questions.
   - Select **Add question** and confirm those existing questions appear in the reusable list.
   - Create a new question with choices or pricing and confirm it is attached to the current type.
   - Open a second appointment type, locate the new question in the reusable list, and attach it.
   - Edit the second type's copy without selecting the template-update checkbox and confirm the first type does not change.
   - Edit again with **Update the reusable template for future attachments**, then attach it to a third type and confirm the third type receives the revised definition while the first type remains unchanged.
