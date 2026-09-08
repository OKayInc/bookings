<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('app:about-m1', function (): void {
    $this->info('Appointment Software M9-R8: tier-limited WebP photo galleries and optional CDN URLs.');
})->purpose('Describe the current appointment software milestone');

Schedule::command('appointments:expire-holds')->everyMinute()->withoutOverlapping();

Schedule::command('appointments:expire-pending-bookings')->everyTenMinutes()->withoutOverlapping();
Schedule::command('appointments:expire-schedule-proposals')->everyTenMinutes()->withoutOverlapping();

Schedule::command('appointments:send-reminders')->everyTenMinutes()->withoutOverlapping();
Schedule::command('appointments:sync-staff-confirmations')->everyTenMinutes()->withoutOverlapping();

Schedule::command('appointments:sync-calendars')->everyFiveMinutes()->withoutOverlapping();

Schedule::command('gallery:normalize-images')->weeklyOn(1, '02:30')->withoutOverlapping();
