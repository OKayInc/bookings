<?php

use App\Http\Controllers\AppointmentContractTemplateController;
use App\Http\Controllers\AppointmentTypeController;
use App\Http\Controllers\AppointmentTypeInvitationController;
use App\Http\Controllers\AppointmentQuestionController;
use App\Http\Controllers\AppointmentTypeCalendarController;
use App\Http\Controllers\CalendarConnectionController;
use App\Http\Controllers\AvailabilityController;
use App\Http\Controllers\AvailabilityExceptionController;
use App\Http\Controllers\AvailabilityPreviewController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\OrganizationController;
use App\Http\Controllers\OrganizationSettingsController;
use App\Http\Controllers\OrganizationInvitationAcceptanceController;
use App\Http\Controllers\OrganizationMemberController;
use App\Http\Controllers\OrganizationHolidayController;
use App\Http\Controllers\PublicAppointmentTypeController;
use App\Http\Controllers\PublicBookingController;
use App\Http\Controllers\PublicBookingManageController;
use App\Http\Controllers\ResourceController;
use App\Http\Controllers\StaffConfirmationController;
use App\Http\Controllers\TicketController;
use App\Http\Controllers\ScheduleProposalController;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('login'));

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store']);
    Route::get('/register', [RegisterController::class, 'create'])->name('register');
    Route::post('/register', [RegisterController::class, 'store']);
});

Route::get('/o/{organizationSlug}', [PublicAppointmentTypeController::class, 'index'])
    ->name('public.appointment-types.index');
Route::get('/o/{organizationSlug}/a/{appointmentSlug}', [PublicAppointmentTypeController::class, 'show'])
    ->name('public.appointment-types.show');
Route::post('/o/{organizationSlug}/a/{appointmentSlug}/unlock', [PublicAppointmentTypeController::class, 'unlockPassword'])
    ->middleware('throttle:10,1')
    ->name('public.appointment-types.unlock');
Route::get('/o/{organizationSlug}/u/{token}', [PublicAppointmentTypeController::class, 'showUnlisted'])
    ->name('public.appointment-types.unlisted');
Route::get('/o/{organizationSlug}/i/{token}', [PublicAppointmentTypeController::class, 'showInvited'])
    ->name('public.appointment-types.invited');


Route::get('/book/type/{appointmentType}/slots', [PublicBookingController::class, 'slots'])
    ->name('public.booking.slots');
Route::post('/book/type/{appointmentType}/holds', [PublicBookingController::class, 'hold'])
    ->middleware('throttle:30,1')
    ->name('public.booking.holds.store');
Route::get('/book/hold/{token}', [PublicBookingController::class, 'editHold'])
    ->name('public.booking-holds.edit');
Route::post('/book/hold/{token}/quote', [PublicBookingController::class, 'quote'])
    ->middleware('throttle:60,1')
    ->name('public.booking-holds.quote');
Route::post('/book/hold/{token}', [PublicBookingController::class, 'store'])
    ->middleware('throttle:20,1')
    ->name('public.booking-holds.store');
Route::get('/book/hold/{token}/contract', [PublicBookingController::class, 'contract'])
    ->name('public.booking-holds.contract');
Route::get('/booking/received/{reference}', [PublicBookingController::class, 'received'])
    ->name('public.bookings.received');
Route::get('/booking/verify/{booking}/{token}', [PublicBookingManageController::class, 'verify'])
    ->middleware('throttle:20,1')
    ->name('public.bookings.verify');
Route::get('/booking/manage/{booking}/{token}', [PublicBookingManageController::class, 'show'])
    ->name('public.bookings.manage');
Route::post('/booking/manage/{booking}/{token}/contract', [PublicBookingManageController::class, 'uploadContract'])
    ->middleware('throttle:20,1')
    ->name('public.bookings.contract.upload');
Route::get('/booking/manage/{booking}/{token}/contract-template', [PublicBookingManageController::class, 'contractTemplate'])
    ->name('public.bookings.contract-template');
Route::get('/booking/manage/{booking}/{token}/signed-files/{file}', [PublicBookingManageController::class, 'signedFile'])
    ->name('public.bookings.signed-file');
Route::get('/booking/manage/{booking}/{token}/answer-files/{file}', [PublicBookingManageController::class, 'answerFile'])
    ->name('public.bookings.answer-file');
Route::get('/booking/manage/{booking}/{token}/tickets/{ticket}', [PublicBookingManageController::class, 'ticket'])
    ->name('public.bookings.tickets.show');
Route::post('/booking/manage/{booking}/{token}/cancel', [PublicBookingManageController::class, 'cancel'])
    ->middleware('throttle:10,1')->name('public.bookings.cancel');
Route::get('/booking/manage/{booking}/{token}/reschedule/slots', [PublicBookingManageController::class, 'rescheduleSlots'])
    ->middleware('throttle:60,1')->name('public.bookings.reschedule.slots');
Route::post('/booking/manage/{booking}/{token}/reschedule', [PublicBookingManageController::class, 'reschedule'])
    ->middleware('throttle:10,1')->name('public.bookings.reschedule');
Route::post('/booking/manage/{booking}/{token}/schedule-proposals/{proposal}', [PublicBookingManageController::class, 'respondScheduleProposal'])
    ->middleware('throttle:20,1')->name('public.bookings.schedule-proposals.respond');

Route::get('/schedule-proposal/{proposal}/{token}', [ScheduleProposalController::class, 'show'])
    ->name('public.schedule-proposals.show');
Route::post('/schedule-proposal/{proposal}/{token}', [ScheduleProposalController::class, 'respond'])
    ->middleware('throttle:20,1')
    ->name('public.schedule-proposals.respond');

Route::get('/staff-confirmation/{confirmation}/{token}', [StaffConfirmationController::class, 'show'])
    ->name('public.staff-confirmations.show');
Route::post('/staff-confirmation/{confirmation}/{token}', [StaffConfirmationController::class, 'respond'])
    ->middleware('throttle:20,1')
    ->name('public.staff-confirmations.respond');

Route::get('/organization-invitation/{token}', [OrganizationInvitationAcceptanceController::class, 'show'])
    ->middleware('throttle:30,1')
    ->name('organization-invitations.show');
Route::post('/organization-invitation/{token}', [OrganizationInvitationAcceptanceController::class, 'accept'])
    ->middleware('throttle:10,1')
    ->name('organization-invitations.accept');

Route::get('/email/verify/{id}/{hash}', function (
    Request $request,
    string $id,
    string $hash
): RedirectResponse {
    $user = User::whereUuid($id)->firstOrFail();

    abort_unless(
        hash_equals(
            sha1($user->getEmailForVerification()),
            $hash
        ),
        403
    );

    if (! $user->hasVerifiedEmail() && $user->markEmailAsVerified()) {
        event(new Verified($user));
    }

    if ($request->user()?->is($user)) {
        return redirect()->route('dashboard')->with('success', 'Email address verified.');
    }

    return redirect()->route('login')
        ->with('success', 'Email address verified. You may now sign in.');
})->middleware(['signed', 'throttle:6,1'])->name('verification.verify');

Route::get('/calendar-connections/oauth/{provider}/callback', [CalendarConnectionController::class, 'callback'])
    ->middleware('throttle:20,1')
    ->name('calendar-connections.callback');

Route::middleware('auth')->group(function (): void {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

    Route::get('/email/verify', fn () => view('auth.verify-email'))
        ->name('verification.notice');
    Route::post('/email/verification-notification', function (Request $request): RedirectResponse {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->route('dashboard');
        }

        $request->user()->sendEmailVerificationNotification();

        return back()->with('message', 'Verification link sent.');
    })->middleware('throttle:6,1')->name('verification.send');
});

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::get('/organizations', [OrganizationController::class, 'index'])->name('organizations.index');
    Route::get('/organizations/create', [OrganizationController::class, 'create'])->name('organizations.create');
    Route::post('/organizations', [OrganizationController::class, 'store'])->name('organizations.store');
    Route::get('/organizations/{organization}/edit', [OrganizationController::class, 'edit'])->name('organizations.edit');
    Route::put('/organizations/{organization}', [OrganizationController::class, 'update'])->name('organizations.update');
    Route::delete('/organizations/{organization}', [OrganizationController::class, 'destroy'])->name('organizations.destroy');
    Route::post('/organizations/{organization}/switch', [OrganizationController::class, 'switch'])->name('organizations.switch');

    Route::middleware('organization')->group(function (): void {
        Route::get('/dashboard', DashboardController::class)->name('dashboard');
        Route::get('/admin/health', HealthController::class)->name('admin.health');
        Route::get('/settings', [OrganizationSettingsController::class, 'edit'])->name('settings.edit');
        Route::put('/settings', [OrganizationSettingsController::class, 'update'])->name('settings.update');

        Route::get('/calendar-connections', [CalendarConnectionController::class, 'index'])->name('calendar-connections.index');
        Route::get('/calendar-connections/resources/{resource}/{provider}/connect', [CalendarConnectionController::class, 'connect'])->name('calendar-connections.connect');
        Route::post('/calendar-connections/{connection}/refresh', [CalendarConnectionController::class, 'refresh'])->name('calendar-connections.refresh');
        Route::delete('/calendar-connections/{connection}', [CalendarConnectionController::class, 'destroy'])->name('calendar-connections.destroy');

        Route::get('/bookings', [BookingController::class, 'index'])->name('bookings.index');
        Route::get('/bookings/{booking}', [BookingController::class, 'show'])->name('bookings.show');
        Route::post('/bookings/{booking}/confirmations/{confirmation}/respond', [BookingController::class, 'respondConfirmation'])->name('bookings.confirmations.respond');
        Route::post('/bookings/{booking}/confirmations/{confirmation}/remind', [BookingController::class, 'remindConfirmation'])->name('bookings.confirmations.remind');
        Route::post('/bookings/{booking}/cancel', [BookingController::class, 'cancel'])->name('bookings.cancel');
        Route::post('/bookings/{booking}/conference/retry', [BookingController::class, 'retryConference'])->name('bookings.conference.retry');
        Route::get('/bookings/{booking}/schedule-proposal/slots', [BookingController::class, 'scheduleProposalSlots'])->name('bookings.schedule-proposals.slots');
        Route::post('/bookings/{booking}/schedule-proposals', [BookingController::class, 'createScheduleProposal'])->name('bookings.schedule-proposals.store');
        Route::post('/bookings/{booking}/schedule-proposals/{proposal}/withdraw', [BookingController::class, 'withdrawScheduleProposal'])->name('bookings.schedule-proposals.withdraw');
        Route::get('/my-confirmations', [StaffConfirmationController::class, 'mine'])->name('staff-confirmations.mine');
        Route::post('/bookings/{booking}/contract-submissions/{submission}/review', [BookingController::class, 'reviewContract'])
            ->name('bookings.contract.review');
        Route::get('/bookings/{booking}/signed-files/{file}', [BookingController::class, 'signedFile'])
            ->name('bookings.signed-file');
        Route::get('/bookings/{booking}/answer-files/{file}', [BookingController::class, 'answerFile'])
            ->name('bookings.answer-file');
        Route::get('/tickets/check-in', [TicketController::class, 'index'])->name('tickets.index');
        Route::post('/tickets/check-in', [TicketController::class, 'checkIn'])->name('tickets.check-in');
        Route::post('/tickets/{ticket}/undo-check-in', [TicketController::class, 'undo'])->name('tickets.undo');
        Route::get('/bookings/{booking}/tickets/{ticket}', [TicketController::class, 'show'])->name('tickets.show');

        Route::get('/availability', [AvailabilityController::class, 'index'])->name('availability.index');
        Route::get('/availability/holidays', [OrganizationHolidayController::class, 'index'])->name('availability.holidays.index');
        Route::patch('/availability/holidays/region', [OrganizationHolidayController::class, 'updateRegion'])->name('availability.holidays.region.update');
        Route::post('/availability/holidays', [OrganizationHolidayController::class, 'store'])->name('availability.holidays.store');
        Route::patch('/availability/holidays/{holiday}/toggle', [OrganizationHolidayController::class, 'toggle'])->name('availability.holidays.toggle');
        Route::delete('/availability/holidays/{holiday}', [OrganizationHolidayController::class, 'destroy'])->name('availability.holidays.destroy');
        Route::get('/availability/organization/edit', [AvailabilityController::class, 'editOrganization'])->name('availability.organization.edit');
        Route::put('/availability/organization', [AvailabilityController::class, 'updateOrganization'])->name('availability.organization.update');
        Route::get('/availability/resources/{resource}/edit', [AvailabilityController::class, 'editResource'])->name('availability.resources.edit');
        Route::put('/availability/resources/{resource}', [AvailabilityController::class, 'updateResource'])->name('availability.resources.update');
        Route::delete('/availability/resources/{resource}', [AvailabilityController::class, 'resetResource'])->name('availability.resources.reset');
        Route::get('/availability/appointment-types/{appointmentType}/edit', [AvailabilityController::class, 'editAppointmentType'])->name('availability.appointment-types.edit');
        Route::put('/availability/appointment-types/{appointmentType}', [AvailabilityController::class, 'updateAppointmentType'])->name('availability.appointment-types.update');
        Route::delete('/availability/appointment-types/{appointmentType}', [AvailabilityController::class, 'resetAppointmentType'])->name('availability.appointment-types.reset');
        Route::post('/availability/schedules/{schedule}/exceptions', [AvailabilityExceptionController::class, 'store'])->name('availability.exceptions.store');
        Route::delete('/availability/schedules/{schedule}/exceptions/{exception}', [AvailabilityExceptionController::class, 'destroy'])->name('availability.exceptions.destroy');
        Route::get('/availability/preview', AvailabilityPreviewController::class)->name('availability.preview');

        Route::patch('/resources/{resource}/organization-settings', [ResourceController::class, 'updateOrganizationSettings'])->name('resources.organization-settings.update');
        Route::resource('resources', ResourceController::class)->except(['show', 'destroy']);
        Route::get('/organization-members', [OrganizationMemberController::class, 'index'])->name('organization-members.index');
        Route::post('/organization-member-invitations', [OrganizationMemberController::class, 'store'])->name('organization-members.invitations.store');
        Route::delete('/organization-member-invitations/{invitation}', [OrganizationMemberController::class, 'destroy'])->name('organization-members.invitations.destroy');
        Route::get('/appointment-types/{appointmentType}/contract-template', [AppointmentContractTemplateController::class, 'download'])
            ->name('appointment-types.contract-template.download');
        Route::post('/appointment-types/{appointmentType}/invitations', [AppointmentTypeInvitationController::class, 'store'])
            ->name('appointment-types.invitations.store');
        Route::delete('/appointment-types/{appointmentType}/invitations/{invitation}', [AppointmentTypeInvitationController::class, 'destroy'])
            ->name('appointment-types.invitations.destroy');
        Route::patch('/appointment-types/{appointmentType}/disable', [AppointmentTypeController::class, 'disable'])
            ->name('appointment-types.disable');
        Route::get('/appointment-types/{appointmentType}/calendars', [AppointmentTypeCalendarController::class, 'edit'])
            ->name('appointment-types.calendars.edit');
        Route::put('/appointment-types/{appointmentType}/calendars', [AppointmentTypeCalendarController::class, 'update'])
            ->name('appointment-types.calendars.update');
        Route::get('/appointment-types/{appointmentType}/questionnaire', [AppointmentQuestionController::class, 'index'])->name('appointment-types.questionnaire.index');
        Route::get('/appointment-types/{appointmentType}/questions/create', [AppointmentQuestionController::class, 'create'])->name('appointment-types.questions.create');
        Route::post('/appointment-types/{appointmentType}/questions', [AppointmentQuestionController::class, 'store'])->name('appointment-types.questions.store');
        Route::post('/appointment-types/{appointmentType}/questions/library/{reusableQuestion}/attach', [AppointmentQuestionController::class, 'attach'])->name('appointment-types.questions.attach');
        Route::get('/appointment-types/{appointmentType}/questions/{question}/edit', [AppointmentQuestionController::class, 'edit'])->name('appointment-types.questions.edit');
        Route::put('/appointment-types/{appointmentType}/questions/{question}', [AppointmentQuestionController::class, 'update'])->name('appointment-types.questions.update');
        Route::delete('/appointment-types/{appointmentType}/questions/{question}', [AppointmentQuestionController::class, 'destroy'])->name('appointment-types.questions.destroy');
        Route::resource('appointment-types', AppointmentTypeController::class)->except(['show']);
    });
});
