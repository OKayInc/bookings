<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('app:about-m1', function (): void {
    $this->info('Appointment Software M9: organization-owned Stripe/PayPal checkout, balances and refunds.');
})->purpose('Describe the current appointment software milestone');

Schedule::command('appointments:expire-holds')->everyMinute()->withoutOverlapping();

Schedule::command('appointments:expire-pending-bookings')->everyTenMinutes()->withoutOverlapping();
Schedule::command('appointments:expire-schedule-proposals')->everyTenMinutes()->withoutOverlapping();

Schedule::command('appointments:send-reminders')->everyTenMinutes()->withoutOverlapping();
Schedule::command('appointments:sync-staff-confirmations')->everyTenMinutes()->withoutOverlapping();

Schedule::command('appointments:sync-calendars')->everyFiveMinutes()->withoutOverlapping();
