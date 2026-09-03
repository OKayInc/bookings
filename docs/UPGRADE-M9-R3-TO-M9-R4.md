# Upgrade M9-R3 to M9-R4

1. Back up the database and application key.
2. Deploy the M9-R4 files.
3. Run `php artisan optimize:clear`.
4. Review `php artisan migrate --pretend` and then run `php artisan migrate`.
5. Run `php artisan test` and `./vendor/bin/pint --test`.
6. Edit a choice question, enable **Conditional resource requirement**, choose its trigger/default answers, fulfillment mode and optional resources, then save.
7. Verify one selected time where the group is available and another where it is not. The second should hide the question and save the configured default.

No data backfill is required. Existing resource requirements and questionnaire answers are unchanged until an administrator configures a conditional rule.
