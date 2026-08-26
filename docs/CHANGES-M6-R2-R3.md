# M6-R2-R3

This revision replaces the complex passwordless booking-management Blade body with normal PHP views after repeated Laravel Blade compilation failures (`unexpected end of file`).

## Fix

- `resources/views/public/bookings/manage.blade.php` is now only a minimal layout/section wrapper.
- The complete booking-management body is rendered from `manage-content.php`, which Laravel treats as a normal PHP view and does not Blade-compile.
- Schedule-proposal rendering moved from `schedule-proposals.blade.php` to `schedule-proposals-content.php`.
- Questionnaire-answer rendering moved from `questionnaire-answers.blade.php` to `questionnaire-answers-content.php`.
- The proposal/client workflow and routes are unchanged.
- `BladeCompilationTest` now compiles only the tiny Blade wrapper and directly PHP-lints every PHP view used by the management page.

No database migrations or schema changes are included.
