<?php

use App\Http\Controllers\OfflineBookingPaymentController;
use Illuminate\Support\Facades\Route;

Route::post('/booking/manage/{booking}/{token}/offline-payment', [OfflineBookingPaymentController::class, 'choose'])
    ->middleware('throttle:20,1')->name('public.offline-payments.choose');
Route::post('/booking/manage/{booking}/{token}/offline-reference', [OfflineBookingPaymentController::class, 'submitReference'])
    ->middleware('throttle:20,1')->name('public.offline-payments.reference');

Route::middleware(['auth', 'verified'])->group(function (): void {
    // Each financial route additionally authorizes the booking's organization in the controller.
    Route::get('/booking-payment-review/{booking}', [OfflineBookingPaymentController::class, 'show'])->name('booking-payment-review.show');
    Route::post('/booking-payment-review/{booking}/refund', [OfflineBookingPaymentController::class, 'refund'])->middleware('throttle:20,1')->name('booking-payment-review.refund');
    Route::post('/booking-payment-review/{booking}/receipts', [OfflineBookingPaymentController::class, 'record'])
        ->middleware('throttle:20,1')->name('booking-payment-review.receipts');
    Route::post('/booking-payment-review/{booking}/extend', [OfflineBookingPaymentController::class, 'extend'])
        ->middleware('throttle:20,1')->name('booking-payment-review.extend');
    Route::post('/booking-payment-review/{booking}/blacklist', [OfflineBookingPaymentController::class, 'blacklist'])
        ->middleware('throttle:20,1')->name('booking-payment-review.blacklist');
    Route::middleware('organization')->group(function (): void {
        Route::get('/payment-reviews', [OfflineBookingPaymentController::class, 'index'])->name('booking-payment-review.index');
        Route::get('/appointment-types/{appointmentType}/offline-payments', [OfflineBookingPaymentController::class, 'editType'])->name('appointment-types.offline-payments.edit');
        Route::put('/appointment-types/{appointmentType}/offline-payments', [OfflineBookingPaymentController::class, 'updateType'])->name('appointment-types.offline-payments.update');
    });
});
