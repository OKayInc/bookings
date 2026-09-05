<?php

namespace App\Http\Controllers;

use App\Domain\Bookings\BookingWorkflowService;
use App\Domain\Bookings\BookingCancellationService;
use App\Domain\Bookings\ResourceConfirmationService;
use App\Domain\Bookings\ContractSubmissionService;
use App\Domain\Bookings\BookingScheduleProposalService;
use App\Domain\Bookings\PublicBookingAvailabilityService;
use App\Domain\Conferences\ConferenceMeetingService;
use App\Domain\Tickets\TicketEventService;
use App\Enums\ContractReviewStatus;
use App\Enums\ResourceConfirmationStatus;
use App\Models\Booking;
use App\Models\BookingContractFile;
use App\Models\BookingAnswerFile;
use App\Models\BookingContractSubmission;
use App\Models\ResourceConfirmation;
use App\Models\BookingScheduleProposal;
use App\Notifications\BookingAccessEmail;
use App\Support\Organizations\OrganizationContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Carbon\CarbonImmutable;
use App\Rules\IanaTimezone;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BookingController extends Controller
{
    public function index(OrganizationContext $context): View
    {
        $organization = $context->organization();
        $this->authorize('manageScheduling', $organization);

        $bookings = $organization->bookings()
            ->with(['appointmentType', 'appointment'])
            ->withCount(['scheduleProposals as schedule_warning_count' => fn ($query) => $query->where('warning_active', true)])
            ->latest()
            ->paginate(50);

        return view('bookings.index', compact('bookings'));
    }

    public function show(Request $request, Booking $booking, OrganizationContext $context): View
    {
        $this->sameOrganization($booking, $context);
        $canManage = Gate::forUser($request->user())->allows('manageScheduling', $context->organization());
        $isAssignedStaff = $this->isAssignedStaff($booking, $request);
        abort_unless($canManage || $isAssignedStaff, 403);
        $canProposeScheduleChange = $canManage || $isAssignedStaff;
        $booking->load(['organization.paymentSettings', 'appointmentType', 'appointment.resources.person', 'contact', 'attendees', 'tickets.attendee', 'tickets.checkedInBy', 'contractTemplate', 'contractSubmissions.files', 'contractSubmissions.reviewedBy', 'answers.files', 'priceLines', 'resourceDeposits', 'resourceConfirmations.resource', 'resourceConfirmations.person', 'resourceConfirmations.respondedBy', 'reschedules', 'scheduleProposals.proposedBy', 'scheduleProposals.hold', 'appointment.externalEvents.calendar.connection.resource', 'payments.refunds', 'refunds.transaction', 'refunds.requestedBy']);
        app(BookingScheduleProposalService::class)->expireForBooking($booking);
        $booking->load('scheduleProposals.proposedBy', 'scheduleProposals.hold');

        return view('bookings.show', [
            'booking' => $booking,
            'canManage' => $canManage,
            'canProposeScheduleChange' => $canProposeScheduleChange,
            'refundableDepositMinor' => app(\App\Domain\Payments\PaymentRefundService::class)->refundableDepositMinor($booking),
            'refundablePriceMinor' => app(\App\Domain\Payments\PaymentRefundService::class)->refundablePriceMinor($booking),
            'timezones' => \DateTimeZone::listIdentifiers(),
        ]);
    }

    public function reviewContract(
        Request $request,
        Booking $booking,
        BookingContractSubmission $submission,
        OrganizationContext $context,
        ContractSubmissionService $contracts,
        BookingWorkflowService $workflow,
    ): RedirectResponse {
        $this->sameOrganization($booking, $context);
        $this->authorize('manageScheduling', $context->organization());
        abort_unless(hash_equals($submission->booking_id, $booking->getKey()), 404);
        $latestSubmission = $booking->contractSubmissions()->first();
        abort_unless($latestSubmission !== null && $latestSubmission->is($submission), 409, 'Only the latest contract submission can be reviewed.');

        $data = $request->validate([
            'status' => ['required', 'in:approved,rejected'],
            'review_notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $contracts->review($submission, ContractReviewStatus::from($data['status']), $request->user()->person, $data['review_notes'] ?? null);
        $workflow->refreshStatus($booking->fresh(['appointmentType', 'contractSubmissions']));

        $manageToken = Str::random(64);
        $booking->update(['manage_token_hash' => hash('sha256', $manageToken, true)]);
        $booking = $booking->fresh(['appointmentType']);
        Notification::route('mail', $booking->email)->notify(
            new BookingAccessEmail($booking, $manageToken, $data['status'] === 'approved'
                ? 'Your signed contract has been approved.'
                : 'Your signed contract needs to be resubmitted. Please review the notes in your booking.')
        );

        return back()->with('success', 'Contract review saved.');
    }

    public function respondConfirmation(
        Request $request,
        Booking $booking,
        ResourceConfirmation $confirmation,
        OrganizationContext $context,
        ResourceConfirmationService $confirmations,
        BookingWorkflowService $workflow,
    ): RedirectResponse {
        $this->sameOrganization($booking, $context);
        abort_unless(hash_equals($confirmation->booking_id, $booking->getKey()), 404);
        $canManage = Gate::forUser($request->user())->allows('manageScheduling', $context->organization());
        $isOwn = $confirmation->person_id !== null && hash_equals($confirmation->person_id, $request->user()->person_id);
        abort_unless($canManage || $isOwn, 403);

        $data = $request->validate([
            'action' => ['required', 'in:accepted,declined'],
            'response_note' => ['nullable', 'string', 'max:5000'],
        ]);

        try {
            $confirmations->respond(
                $confirmation,
                ResourceConfirmationStatus::from($data['action']),
                $data['response_note'] ?? null,
                $request->user()->person,
            );
        } catch (\RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        $workflow->refreshStatus($booking->fresh(['appointmentType', 'contractSubmissions', 'appointment.resources.person']));
        return back()->with('success', 'Staff response saved.');
    }

    public function remindConfirmation(
        Booking $booking,
        ResourceConfirmation $confirmation,
        OrganizationContext $context,
        ResourceConfirmationService $confirmations,
    ): RedirectResponse {
        $this->sameOrganization($booking, $context);
        $this->authorize('manageScheduling', $context->organization());
        abort_unless(hash_equals($confirmation->booking_id, $booking->getKey()), 404);
        try {
            $confirmations->sendReminder($confirmation);
        } catch (\RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Confirmation reminder sent.');
    }

    public function cancel(
        Request $request,
        Booking $booking,
        OrganizationContext $context,
        BookingCancellationService $cancellations,
    ): RedirectResponse {
        $this->sameOrganization($booking, $context);
        $this->authorize('manageScheduling', $context->organization());
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:5000']]);
        try {
            $cancellations->cancelByStaff($booking->load('appointment'), $data['reason'] ?? null);
        } catch (\RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Booking cancelled.');
    }

    public function retryConference(
        Booking $booking,
        OrganizationContext $context,
        ConferenceMeetingService $conferenceMeetings,
    ): RedirectResponse {
        $this->sameOrganization($booking, $context);
        $this->authorize('manageScheduling', $context->organization());
        abort_if($booking->appointment->meeting_provider === null, 404);

        $conferenceMeetings->safeSync($booking->appointment);
        $appointment = $booking->appointment->fresh();

        return back()->with(
            $appointment->meeting_status === 'ready' ? 'success' : 'error',
            $appointment->meeting_status === 'ready'
                ? 'Online meeting is ready.'
                : 'The meeting provider could not create a meeting. Review the provider error and organization settings.',
        );
    }

    public function scheduleProposalSlots(
        Request $request,
        Booking $booking,
        OrganizationContext $context,
        PublicBookingAvailabilityService $availability,
        TicketEventService $ticketEvents,
    ): JsonResponse {
        $this->sameOrganization($booking, $context);
        $this->assertCanPropose($request, $booking, $context);
        $data = $request->validate([
            'timezone' => ['required', 'string', new IanaTimezone],
            'date' => ['required', 'date_format:Y-m-d'],
        ]);
        $booking->loadMissing(['appointmentType.organization', 'appointment']);
        $localDay = CarbonImmutable::parse($data['date'].' 00:00:00', $data['timezone'])->startOfDay();
        if ($localDay->lt(now($data['timezone'])->startOfDay())) {
            return response()->json(['message' => 'The selected date is in the past.'], 422);
        }

        $slots = array_values(array_filter($availability->slots(
            $booking->appointmentType,
            $localDay->utc(),
            $localDay->addDay()->utc(),
            (int) $booking->appointment->duration_value,
            $data['timezone'],
            (int) $booking->attendee_count,
            false,
        ), fn ($slot): bool => $slot->startsAtUtc->isFuture()
            && ! $slot->startsAtUtc->equalTo($booking->appointment->starts_at_utc)));

        return response()->json(['slots' => array_map(function ($slot) use ($booking, $data, $ticketEvents): array {
            $clientStart = $slot->startsAtUtc->setTimezone($data['timezone']);
            $clientEnd = $slot->endsAtUtc->setTimezone($data['timezone']);
            $orgStart = $slot->startsAtUtc->setTimezone($booking->organization->timezone);
            $orgEnd = $slot->endsAtUtc->setTimezone($booking->organization->timezone);
            $clientLabel = $clientStart->format('D, M j · g:i A').' – '.$clientEnd->format('g:i A');
            $organizationLabel = $orgStart->format('D, M j · g:i A').' – '.$orgEnd->format('g:i A');
            if ($booking->appointmentType->ticketing_enabled) {
                $event = $ticketEvents->appointmentAttributes($booking->appointmentType, $slot->startsAtUtc, $slot->endsAtUtc);
                $clientShowStart = $event['show_starts_at_utc']->setTimezone($data['timezone']);
                $clientShowEnd = $event['show_ends_at_utc']?->setTimezone($data['timezone']);
                $orgShowStart = $event['show_starts_at_utc']->setTimezone($booking->organization->timezone);
                $orgShowEnd = $event['show_ends_at_utc']?->setTimezone($booking->organization->timezone);
                $clientLabel = $clientStart->format('D, M j').' · Doors '.$clientStart->format('g:i A').' · Show '.$clientShowStart->format('g:i A').($clientShowEnd ? ' – '.$clientShowEnd->format('g:i A') : '');
                $organizationLabel = $orgStart->format('D, M j').' · Doors '.$orgStart->format('g:i A').' · Show '.$orgShowStart->format('g:i A').($orgShowEnd ? ' – '.$orgShowEnd->format('g:i A') : '');
            }
            return [
                'starts_at_utc' => $slot->startsAtUtc->toIso8601String(),
                'client_label' => $clientLabel,
                'organization_label' => $organizationLabel,
                'remaining_capacity' => $slot->remainingCapacity,
            ];
        }, $slots)]);
    }

    public function createScheduleProposal(
        Request $request,
        Booking $booking,
        OrganizationContext $context,
        BookingScheduleProposalService $proposals,
    ): RedirectResponse {
        $this->sameOrganization($booking, $context);
        $this->assertCanPropose($request, $booking, $context);
        $data = $request->validate([
            'timezone' => ['required', 'string', new IanaTimezone],
            'starts_at_utc' => ['required', 'date'],
            'expires_hours' => ['required', 'integer', 'min:1', 'max:'.max(1, (int) config('booking.schedule_proposal_max_ttl_hours', 168))],
            'reason' => ['nullable', 'string', 'max:5000'],
            'client_message' => ['nullable', 'string', 'max:5000'],
        ]);

        try {
            $proposals->create(
                $booking,
                $request->user()->person,
                CarbonImmutable::parse($data['starts_at_utc'])->utc(),
                $data['timezone'],
                (int) $data['expires_hours'],
                $data['reason'] ?? null,
                $data['client_message'] ?? null,
            );
        } catch (\RuntimeException|\InvalidArgumentException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Schedule-change proposal sent to the client. The proposed time is reserved until the proposal expires.');
    }

    public function withdrawScheduleProposal(
        Request $request,
        Booking $booking,
        BookingScheduleProposal $proposal,
        OrganizationContext $context,
        BookingScheduleProposalService $proposals,
    ): RedirectResponse {
        $this->sameOrganization($booking, $context);
        abort_unless(hash_equals($proposal->booking_id, $booking->getKey()), 404);
        $canManage = Gate::forUser($request->user())->allows('manageScheduling', $context->organization());
        $isProposer = $proposal->proposed_by_person_id !== null && hash_equals($proposal->proposed_by_person_id, $request->user()->person_id);
        abort_unless($canManage || $isProposer, 403);
        try {
            $proposals->withdraw($proposal);
        } catch (\RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Schedule-change proposal withdrawn and its alternative-time hold released.');
    }

    public function signedFile(
        Booking $booking,
        BookingContractFile $file,
        OrganizationContext $context,
    ): StreamedResponse {
        $this->sameOrganization($booking, $context);
        $canManage = Gate::forUser(request()->user())->allows('manageScheduling', $context->organization());
        $isAssignedStaff = $this->isAssignedStaff($booking, request());
        abort_unless($canManage || $isAssignedStaff, 403);
        $file->loadMissing('submission');
        abort_unless(hash_equals($file->submission->booking_id, $booking->getKey()), 404);
        abort_unless(Storage::disk($file->disk)->exists($file->path), 404);

        return Storage::disk($file->disk)->download($file->path, $file->original_name);
    }


    public function answerFile(Booking $booking, BookingAnswerFile $file, OrganizationContext $context): StreamedResponse
    {
        $this->sameOrganization($booking, $context);
        $canManage = Gate::forUser(request()->user())->allows('manageScheduling', $context->organization());
        $isAssignedStaff = $this->isAssignedStaff($booking, request());
        abort_unless($canManage || $isAssignedStaff, 403);
        abort_unless(hash_equals($file->booking_id, $booking->getKey()), 404);
        abort_unless(Storage::disk($file->disk)->exists($file->path), 404);
        return Storage::disk($file->disk)->download($file->path, $file->original_name);
    }

    private function isAssignedStaff(Booking $booking, Request $request): bool
    {
        return $booking->appointment()
            ->whereHas('resources', fn ($query) => $query->where('resources.person_id', $request->user()->person_id))
            ->exists();
    }

    private function assertCanPropose(Request $request, Booking $booking, OrganizationContext $context): void
    {
        $canManage = Gate::forUser($request->user())->allows('manageScheduling', $context->organization());
        $isAssignedStaff = $this->isAssignedStaff($booking, $request);
        abort_unless($canManage || $isAssignedStaff, 403);
    }

    private function sameOrganization(Booking $booking, OrganizationContext $context): void
    {
        abort_unless(hash_equals($booking->organization_id, $context->organization()->getKey()), 404);
    }
}
