<?php

use App\Console\Commands\ExpireBookingHoldsCommand;
use App\Console\Commands\ExpirePendingBookingsCommand;
use App\Console\Commands\ExpireScheduleProposalsCommand;
use App\Console\Commands\SyncExternalCalendarsCommand;
use App\Console\Commands\TimezoneHealthCommand;
use App\Console\Commands\SendAppointmentRemindersCommand;
use App\Console\Commands\SyncStaffConfirmationsCommand;
use App\Console\Commands\NormalizeGalleryImagesCommand;
use App\Exceptions\ExpiredBookingHoldException;
use App\Http\Middleware\EnsureActiveOrganization;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

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
        NormalizeGalleryImagesCommand::class,
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
        $exceptions->render(function (ExpiredBookingHoldException $exception, Request $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => $exception->getMessage(),
                    'return_url' => $exception->returnUrl,
                ], $exception->getStatusCode())->header('Cache-Control', 'no-store, private');
            }

            return response()->view('errors.booking-hold-expired', [
                'organization' => $exception->hold->organization,
                'type' => $exception->hold->appointmentType,
                'returnUrl' => $exception->returnUrl,
            ], $exception->getStatusCode())->header('Cache-Control', 'no-store, private');
        });
    })->create();
