<?php

use App\Http\Controllers\ResourceCalendarDefaultsController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'organization'])->group(function (): void {
    Route::put('/calendar-connections/resources/{resource}/defaults', [ResourceCalendarDefaultsController::class, 'update'])
        ->name('calendar-connections.defaults.update');
});
