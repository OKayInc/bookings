# Upgrade M7-R19 to M7-R20

1. Back up MariaDB, `.env`, `APP_KEY`, and runtime `storage`.
2. Apply `m7-r19-to-m7-r20.patch` at the M7-R19 application root, or deploy the full M7-R20 source ZIP while preserving configuration and runtime files. Include `public/js/numeric-question-constraints.js`.
3. Install existing dependencies, run the additive migration, and clear caches:

```bash
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan optimize:clear
```

Do not run `migrate:fresh` on an existing installation. No new Composer packages or environment values are required. Existing questions start with no numeric constraints.

## Verify on a dedicated test host

Install development dependencies with `composer install`. Use the configured MariaDB testing connection and a separate database whose name contains `test`; never point the suite at production.

```bash
php artisan test --filter=NumericComparisonTest
php artisan test --filter=NumericQuestionConstraintTest
php artisan test --filter=QuestionnaireBookingFlowTest
php artisan test --filter=DependentQuestionTest
php artisan test --filter=ReusableQuestionTest
php artisan test --filter=QuestionnaireConfigurationTest
php artisan test --filter=OrganizationDeletionTest
php artisan test --filter=BladeCompilationTest
php artisan test
node --test tests/JavaScript/*.test.cjs
```

### Manual checks

1. Add numeric Q1 and Q2. On Q2, require `this answer >= Q1`. In checkout, Q1=5/Q2=4 must fail, and Q1=5/Q2=5 must pass. Changing Q1 to 6 must immediately invalidate Q2 again.
2. Add `AND this answer < 10`, then `OR this answer = 0`. Check the grouped preview, equality boundaries, and both alternative groups. Check `!=` with equal and different decimal values.
3. Hide Q1 using a display dependency. A forged/stale hidden Q1 answer must not satisfy Q2's comparison. Hide Q2 and verify its stale value/error is cleared. Check a direct POST with JavaScript disabled as well.
4. Edit the saved rules; remove all rows and save. Switch between Number and other field types, and between a question operand and fixed number. Only active fields should submit.
5. Confirm source deletion/disable/type changes/reordering are blocked while referenced, and that attaching a reusable copy to another type starts without numeric constraints.
6. Check the editor and public form on desktop/mobile, including keyboard validation and inline error announcements.

## Rollback

M7-R19 does not enforce these constraints. Stop bookings or deliberately remove/relax affected rules before reverting the application code; otherwise reverting silently stops enforcement. Rolling back the new migration deletes constraint configuration only, not questions, bookings, or saved answers. Retain the database backup if the rules need to be restored. Clear caches after reverting.
