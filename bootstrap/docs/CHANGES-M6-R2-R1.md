# M6-R2 Revision 1

Fixes a Blade compilation failure on the passwordless booking-management page when a pending staff schedule-change proposal is present.

## Fix

- Moves pending/warning schedule-proposal selection from `manage.blade.php` into `PublicBookingManageController::show()`.
- Rewrites the schedule-proposal UI block using explicit multi-line Blade directives.
- Keeps the client choices unchanged: Accept proposed time, Keep original time, Cancel booking.
- No schema, route, or workflow changes.

## Upgrade

No migration is required.

```bash
php artisan optimize:clear
php artisan view:clear
php artisan test --filter=StaffScheduleProposalTest
php artisan test
```
