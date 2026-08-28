<?php

namespace App\Http\Controllers;

use App\Domain\Bookings\BookingWorkflowService;
use App\Domain\Bookings\BookingCancellationService;
use App\Domain\Bookings\BookingPolicyService;
use App\Domain\Bookings\BookingRescheduleService;
use App\Domain\Bookings\BookingScheduleProposalService;
use App\Domain\Bookings\PublicBookingAvailabilityService;
use App\Domain\Bookings\PublicBookingHoldService;
use App\Domain\Bookings\ContractSubmissionService;
use App\Enums\ContractReviewStatus;
use App\Models\Booking;
use App\Models\BookingContractFile;
use App\Models\BookingAnswerFile;
use App\Models\BookingScheduleProposal;
use App\Notifications\BookingAccessEmail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\CarbonImmutable;
use App\Rules\IanaTimezone;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PublicBookingManageController extends Controller
{
    public function verify(Booking $booking, string $token, BookingWorkflowService $workflow): RedirectResponse
    {
        $manageToken = Str::random(64);

        DB::transaction(function () use ($booking, $token, $workflow, $manageToken): void {
            $locked = Booking::query()->whereKey($booking->getKey())->lockForUpdate()->firstOrFail();
            abort_if($locked->email_verified_at !== null, 410, 'This verification link has already been used.');
            abort_if($locked->email_verification_expires_at_utc?->isPast(), 410, 'This verification link has expired.');
            abort_unless($locked->email_verification_token_hash !== null
                && hash_equals($locked->email_verification_token_hash, hash('sha256', $token, true)), 404);

            $locked->update([
                'email_verified_at' => now('UTC'),
                'email_verification_token_hash' => null,
                'email_verification_expires_at_utc' => null,
                'expires_at_utc' => null,
                'manage_token_hash' => hash('sha256', $manageToken, true),
            ]);
            $locked->contact()->update(['email_verified_at' => now('UTC')]);
            $workflow->refreshStatus($locked->fresh(['appointmentType', 'contractSubmissions']));
        });

        $booking = $booking->fresh(['appointmentType', 'organization']);
        Notification::route('mail', $booking->email)->notify(
            new BookingAccessEmail($booking, $manageToken, 'Your email address has been verified.')
        );

        if ($booking->status->value === 'confirmed' && $booking->appointmentType->redirect_url) {
            return redirect()->away($booking->appointmentType->redirect_url);
        }

        return redirect()->route('public.bookings.manage', [$booking, $manageToken]);
    }

    public function show(
        Booking $booking,
        string $token,
        BookingPolicyService $policy,
        BookingScheduleProposalService $proposals,
    ): View {
        $this->authorizeToken($booking, $token);
        $proposals->expireForBooking($booking);
        $booking->load(['organization', 'appointmentType', 'appointment', 'attendees', 'contractTemplate', 'contractSubmissions.files', 'answers.files', 'priceLines', 'resourceConfirmations', 'reschedules', 'scheduleProposals.proposedBy']);

        $pendingProposal = $booking->scheduleProposals->first(function (BookingScheduleProposal $proposal): bool {
            return $proposal->status->value === 'pending' && $proposal->expires_at_utc->isFuture();
        });
        $warningProposals = $booking->scheduleProposals->filter(function (BookingScheduleProposal $proposal): bool {
            return (bool) $proposal->warning_active;
        });

        return view('public.bookings.manage', [
            'booking' => $booking,
            'organization' => $booking->organization,
            'manageToken' => $token,
            'latestSubmission' => $booking->contractSubmissions->sortByDesc('submitted_at_utc')->first(),
            'canCancel' => $policy->canCancel($booking),
            'canReschedule' => $policy->canReschedule($booking) && $pendingProposal === null,
            'cancellationStatus' => $policy->cancellationStatus($booking),
            'reschedulingStatus' => $policy->reschedulingStatus($booking),
            'pendingProposal' => $pendingProposal,
            'warningProposals' => $warningProposals,
            'policy' => $policy,
            'timezones' => \DateTimeZone::listIdentifiers(),
        ]);
    }

    public function uploadContract(
        Request $request,
        Booking $booking,
        string $token,
        ContractSubmissionService $contracts,
        BookingWorkflowService $workflow,
    ): RedirectResponse {
        $this->authorizeToken($booking, $token);
        abort_if($booking->contract_template_id === null, 404);

        $request->validate([
            'contract_files' => ['required', 'array', 'min:1', 'max:'.config('contracts.max_signed_files', 30)],
            'contract_files.*' => [
                'required', 'file',
                'mimes:'.implode(',', config('contracts.signed_extensions', ['pdf', 'jpg', 'jpeg', 'png', 'webp'])),
                'max:'.config('contracts.max_signed_file_kilobytes', 20480),
            ],
        ]);
        $files = array_values($request->file('contract_files', []));
        $pdfCount = collect($files)->filter(fn ($file) => strtolower($file->getClientOriginalExtension()) === 'pdf')->count();
        if ($pdfCount > 0 && count($files) !== 1) {
            return back()->withErrors(['contract_files' => 'Upload either one PDF or one or more page images, not a mixture.']);
        }

        $latest = $booking->contractSubmissions()->first();
        abort_if($latest !== null && $latest->status !== ContractReviewStatus::Rejected, 409, 'The current contract submission is still under review or has already been approved.');

        $contracts->submit($booking->load('organization'), $files);
        $workflow->refreshStatus($booking->fresh(['appointmentType', 'contractSubmissions']));

        return back()->with('success', 'Signed contract uploaded for manual review.');
    }

    public function cancel(
        Request $request,
        Booking $booking,
        string $token,
        BookingCancellationService $cancellations,
    ): RedirectResponse {
        $this->authorizeToken($booking, $token);
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:5000']]);
        try {
            $cancellations->cancelByClient($booking->load(['appointment', 'organization']), $data['reason'] ?? null);
        } catch (\RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Your booking has been cancelled.');
    }

    public function rescheduleSlots(
        Request $request,
        Booking $booking,
        string $token,
        BookingPolicyService $policy,
        PublicBookingAvailabilityService $availability,
    ): JsonResponse {
        $this->authorizeToken($booking, $token);
        if ($booking->scheduleProposals()->where('status', 'pending')->where('expires_at_utc', '>', now('UTC'))->exists()) {
            return response()->json(['message' => 'Please respond to the active staff schedule-change proposal before starting another reschedule.'], 409);
        }
        if (! $policy->canReschedule($booking)) {
            return response()->json(['message' => $policy->reschedulingStatus($booking)], 409);
        }
        $data = $request->validate([
            'timezone' => ['required', 'string', new IanaTimezone],
            'date' => ['required', 'date_format:Y-m-d'],
        ]);
        $booking->loadMissing(['appointmentType.organization', 'appointment']);
        $localDay = CarbonImmutable::parse($data['date'].' 00:00:00', $data['timezone'])->startOfDay();
        if ($localDay->lt(now($data['timezone'])->startOfDay())) {
            return response()->json(['message' => 'The selected date is in the past.'], 422);
        }
        $slots = $availability->slots(
            $booking->appointmentType,
            $localDay->utc(),
            $localDay->addDay()->utc(),
            (int) $booking->appointment->duration_value,
            $data['timezone'],
            (int) $booking->attendee_count,
        );

        return response()->json(['slots' => array_map(function ($slot) use ($booking, $data): array {
            $clientStart = $slot->startsAtUtc->setTimezone($data['timezone']);
            $clientEnd = $slot->endsAtUtc->setTimezone($data['timezone']);
            return [
                'starts_at_utc' => $slot->startsAtUtc->toIso8601String(),
                'client_label' => $clientStart->format('D, M j · g:i A').' – '.$clientEnd->format('g:i A'),
                'remaining_capacity' => $slot->remainingCapacity,
            ];
        }, $slots)]);
    }

    public function reschedule(
        Request $request,
        Booking $booking,
        string $token,
        BookingPolicyService $policy,
        PublicBookingHoldService $holds,
        BookingRescheduleService $reschedules,
    ): RedirectResponse {
        $this->authorizeToken($booking, $token);
        if ($booking->scheduleProposals()->where('status', 'pending')->where('expires_at_utc', '>', now('UTC'))->exists()) {
            return back()->with('error', 'Please respond to the active staff schedule-change proposal before starting another reschedule.');
        }
        if (! $policy->canReschedule($booking)) {
            return back()->with('error', $policy->reschedulingStatus($booking));
        }
        $data = $request->validate([
            'timezone' => ['required', 'string', new IanaTimezone],
            'starts_at_utc' => ['required', 'date'],
            'reason' => ['nullable', 'string', 'max:5000'],
        ]);
        $booking->loadMissing(['appointmentType.organization', 'appointment']);
        try {
            $lease = $holds->acquire(
                $booking->appointmentType,
                CarbonImmutable::parse($data['starts_at_utc'])->utc(),
                (int) $booking->appointment->duration_value,
                $data['timezone'],
                (int) $booking->attendee_count,
            );
            $reschedules->applyFromHold($booking, $lease->token, true, null, $data['reason'] ?? null);
        } catch (\RuntimeException|\InvalidArgumentException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Your booking has been rescheduled.');
    }

    public function respondScheduleProposal(
        Request $request,
        Booking $booking,
        string $token,
        BookingScheduleProposal $proposal,
        BookingScheduleProposalService $proposals,
    ): RedirectResponse {
        $this->authorizeToken($booking, $token);
        abort_unless(hash_equals($proposal->booking_id, $booking->getKey()), 404);
        $data = $request->validate([
            'action' => ['required', 'in:accept,keep,cancel'],
            'reason' => ['nullable', 'string', 'max:5000'],
        ]);
        try {
            match ($data['action']) {
                'accept' => $proposals->accept($proposal),
                'keep' => $proposals->keepOriginal($proposal),
                'cancel' => $proposals->cancelBooking($proposal, $data['reason'] ?? null),
            };
        } catch (\RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', match ($data['action']) {
            'accept' => 'The proposed time was accepted and your booking was updated.',
            'keep' => 'Your original appointment time remains unchanged. The staff availability warning is still visible.',
            'cancel' => 'Your booking was cancelled because of the staff schedule issue.',
        });
    }

    public function contractTemplate(Booking $booking, string $token): StreamedResponse
    {
        $this->authorizeToken($booking, $token);
        $template = $booking->contractTemplate()->firstOrFail();
        abort_unless(Storage::disk($template->disk)->exists($template->path), 404);

        return Storage::disk($template->disk)->download($template->path, $template->original_name);
    }

    public function signedFile(Booking $booking, string $token, BookingContractFile $file): StreamedResponse
    {
        $this->authorizeToken($booking, $token);
        $file->loadMissing('submission');
        abort_unless(hash_equals($file->submission->booking_id, $booking->getKey()), 404);
        abort_unless(Storage::disk($file->disk)->exists($file->path), 404);

        return Storage::disk($file->disk)->download($file->path, $file->original_name);
    }


    public function answerFile(Booking $booking, string $token, BookingAnswerFile $file): StreamedResponse
    {
        $this->authorizeToken($booking, $token);
        abort_unless(hash_equals($file->booking_id, $booking->getKey()), 404);
        abort_unless(Storage::disk($file->disk)->exists($file->path), 404);
        return Storage::disk($file->disk)->download($file->path, $file->original_name);
    }

    private function authorizeToken(Booking $booking, string $token): void
    {
        abort_unless(hash_equals($booking->manage_token_hash, hash('sha256', $token, true)), 404);
    }
}
