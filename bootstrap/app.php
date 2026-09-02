<?php

use App\Console\Commands\ExpireBookingHoldsCommand;
use App\Console\Commands\ExpirePendingBookingsCommand;
use App\Console\Commands\ExpireScheduleProposalsCommand;
use App\Console\Commands\SyncExternalCalendarsCommand;
use App\Console\Commands\TimezoneHealthCommand;
use App\Console\Commands\SendAppointmentRemindersCommand;
use App\Console\Commands\SyncStaffConfirmationsCommand;
use App\Http\Middleware\EnsureActiveOrganization;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withCommands([
        TimezoneHealthCommand::class,
        ExpireBookingHoldsCommand::class,
        ExpirePendingBookingsCommand::class,
        ExpireScheduleProposalsCommand::class,
        SyncExternalCalendarsCommand::class,
        SendAppointmentRemindersCommand::class,
        SyncStaffConfirmationsCommand::class,
    ])
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->validateCsrfTokens(except: [
            'payments/webhooks/*',
        ]);
        $middleware->alias([
            'organization' => EnsureActiveOrganization::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // M1 intentionally uses Laravel's default exception handling.
    })->create();
