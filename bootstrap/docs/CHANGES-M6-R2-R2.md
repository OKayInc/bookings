# M6-R2-R2

- Fixes the persistent Blade `unexpected end of file` error on the passwordless booking management page.
- Restores the known-working M6-R1 `manage.blade.php` structure.
- Moves staff schedule-proposal rendering into `public/bookings/partials/schedule-proposals.blade.php`.
- Uses plain PHP control structures inside the proposal partial to avoid Blade directive parsing ambiguity.
- PublicBookingManageController now suppresses ordinary client rescheduling while a staff proposal is pending.
- Adds `BladeCompilationTest`, which compiles the affected Blade source through Laravel's Blade compiler and runs `php -l` on the compiled PHP.
- No database migrations or schema changes.
