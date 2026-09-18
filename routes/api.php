<?php

use App\Http\Controllers\Api\SchedulingController;
use App\Http\Middleware\AuthenticateApiKeys;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware(['throttle:60,1', AuthenticateApiKeys::class])->group(function (): void {
    Route::get('/me', [SchedulingController::class, 'me']);
    Route::get('/resources', [SchedulingController::class, 'resources']);
    Route::get('/appointment-types', [SchedulingController::class, 'types']);
    Route::get('/appointment-types/{uuid}/availability', [SchedulingController::class, 'availability'])->whereUuid('uuid');
    Route::patch('/appointment-types/{uuid}/disable', [SchedulingController::class, 'disable'])->whereUuid('uuid');
    Route::get('/bookings', [SchedulingController::class, 'bookings']);
    Route::get('/bookings/{uuid}', [SchedulingController::class, 'booking'])->whereUuid('uuid');
});
