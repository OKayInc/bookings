<?php

use App\Console\Commands\ExpireBookingHoldsCommand;
use App\Console\Commands\ExpirePendingBookingsCommand;
use App\Console\Commands\ExpireScheduleProposalsCommand;
use App\Console\Commands\SyncExternalCalendarsCommand;
use App\Console\Commands\TimezoneHealthCommand;
use App\Console\Commands\SendAppointmentRemindersCommand;
use App\Console\Commands\SyncStaffConfirmationsCommand;
use App\Console\Commands\NormalizeGalleryImagesCommand;
use App\Console\Commands\ApplyPlanAddonChangesCommand;
use App\Http\Middleware\EnsureActiveOrganization;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: [__DIR__.'/../routes/web.php', __DIR__.'/../routes/calendar-defaults.php'],
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withCommands([
        TimezoneHealthCommand::class,
        \App\Console\Commands\DispatchWebhooksCommand::class,
        \App\Console\Commands\PurgeOrganizationCommand::class,
        ExpireBookingHoldsCommand::class,
        ExpirePendingBookingsCommand::class,
        ExpireScheduleProposalsCommand::class,
        SyncExternalCalendarsCommand::class,
        SendAppointmentRemindersCommand::class,
        SyncStaffConfirmationsCommand::class,
        NormalizeGalleryImagesCommand::class,
        ApplyPlanAddonChangesCommand::class,
    ])
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->validateCsrfTokens(except: [
            'payments/webhooks/*',
            'plans/stripe/webhook',
        ]);
        $middleware->alias([
            'organization' => EnsureActiveOrganization::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (\App\Domain\Plans\PlanLimitException $exception, \Illuminate\Http\Request $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json(['message' => $exception->getMessage()], 422);
            }

            return redirect()->back()->withInput()->withErrors(['plan' => $exception->getMessage()]);
        });
        $exceptions->render(function (\Illuminate\Http\Exceptions\PostTooLargeException $exception, \Illuminate\Http\Request $request) {
            $message = 'The upload exceeds the server request limit. Upload fewer or smaller files, or ask the administrator to increase PHP post_max_size and upload_max_filesize.';
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json(['message' => $message], 413);
            }
            return response()->view('errors.upload-too-large', ['message' => $message], 413);
        });
        $exceptions->shouldRenderJsonWhen(fn (\Illuminate\Http\Request $request, \Throwable $e) => $request->is('api/*') || $request->expectsJson());
    })->create();
