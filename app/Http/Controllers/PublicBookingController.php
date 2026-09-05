<?php

namespace App\Http\Controllers;

use App\Domain\Appointments\AppointmentTypePricingService;
use App\Domain\Bookings\BookingCreationService;
use App\Domain\Bookings\PublicAppointmentAccessService;
use App\Domain\Bookings\PublicBookingAvailabilityService;
use App\Domain\Bookings\PublicBookingHoldService;
use App\Domain\Money\MoneyService;
use App\Domain\Resources\EquipmentPricingService;
use App\Domain\Resources\ResourceDepositService;
use App\Domain\Resources\ConditionalResourceRequirementService;
use App\Domain\Questionnaires\QuestionnaireSubmissionService;
use App\Domain\Tickets\TicketEventService;
use App\Domain\Tickets\TicketInventoryService;
use App\Domain\Coupons\CouponRedemptionService;
use App\Domain\Questionnaires\QuestionnaireSubmission;
use App\Enums\AttendanceMode;
use App\Models\AppointmentType;
use App\Models\BookingHold;
use App\Notifications\BookingAccessEmail;
use App\Notifications\VerifyBookingEmail;
use App\Rules\IanaTimezone;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PublicBookingController extends Controller
{
    public function slots(
        Request $request,
        AppointmentType $appointmentType,
        PublicAppointmentAccessService $access,
        PublicBookingAvailabilityService $availability,
        AppointmentTypePricingService $pricing,
        MoneyService $money,
        TicketEventService $ticketEvents,
        TicketInventoryService $ticketInventory,
        EquipmentPricingService $equipmentPricing,
        ResourceDepositService $resourceDeposits,
    ): JsonResponse {
        $data = $request->validate([
            'access_mode' => ['required', Rule::in(['direct', 'unlisted', 'invitation'])],
            'access_token' => ['nullable', 'string', 'max:128'],
            'timezone' => ['required', 'string', new IanaTimezone],
            'date' => ['required', 'date_format:Y-m-d'],
            'duration_value' => ['nullable', 'integer', 'min:1'],
            'attendee_count' => ['required', 'integer', 'min:1'],
        ]);

        $access->resolve($appointmentType, $request, $data['access_mode'], $data['access_token'] ?? null);
        $this->validateAttendeeCount($appointmentType, (int) $data['attendee_count']);

        $timezone = $data['timezone'];
        $localDay = CarbonImmutable::parse($data['date'].' 00:00:00', $timezone)->startOfDay();

        $today = now($timezone)->startOfDay();
        if ($localDay->lt($today)) {
            return response()->json(['message' => 'The selected date is in the past.'], 422);
        }

        $duration = isset($data['duration_value']) ? (int) $data['duration_value'] : null;
        try {
            $slots = $availability->slots(
                $appointmentType->loadMissing('organization'),
                $localDay->utc(),
                $localDay->addDay()->utc(),
                $duration,
                $timezone,
                (int) $data['attendee_count'],
            );
            $equipmentTotal = $equipmentPricing->total($appointmentType);
            $depositTotal = $resourceDeposits->total($appointmentType);
            $price = $pricing->priceForBooking($appointmentType, $duration, $appointmentType->duration_unit, (int) $data['attendee_count']);
            if ($equipmentTotal > PHP_INT_MAX - $price) {
                throw new \InvalidArgumentException('The appointment price is too large.');
            }
            $price += $equipmentTotal;
            if ($depositTotal > PHP_INT_MAX - $price) {
                throw new \InvalidArgumentException('The appointment price is too large.');
            }
            $price += $depositTotal;
            if ($appointmentType->ticketing_enabled) {
                foreach ($slots as $slot) {
                    $ticketEvents->appointmentAttributes($appointmentType, $slot->startsAtUtc, $slot->endsAtUtc);
                }
            }
        } catch (RuntimeException|\InvalidArgumentException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        try {
            $slotPayloads = array_map(function ($slot) use (
                $timezone,
                $appointmentType,
                $ticketEvents,
                $ticketInventory,
                $pricing,
                $money,
                $duration,
                $data,
                $equipmentTotal,
                $depositTotal,
            ): array {
                $clientStart = $slot->startsAtUtc->setTimezone($timezone);
                $clientEnd = $slot->endsAtUtc->setTimezone($timezone);
                $orgStart = $slot->startsAtUtc->setTimezone($appointmentType->organization->timezone);
                $orgEnd = $slot->endsAtUtc->setTimezone($appointmentType->organization->timezone);
                $event = $appointmentType->ticketing_enabled
                    ? $ticketEvents->appointmentAttributes($appointmentType, $slot->startsAtUtc, $slot->endsAtUtc)
                    : null;
                $clientShowStart = $event === null ? null : $event['show_starts_at_utc']->setTimezone($timezone);
                $clientShowEnd = $event === null || $event['show_ends_at_utc'] === null ? null : $event['show_ends_at_utc']->setTimezone($timezone);
                $orgShowStart = $event === null ? null : $event['show_starts_at_utc']->setTimezone($appointmentType->organization->timezone);
                $orgShowEnd = $event === null || $event['show_ends_at_utc'] === null ? null : $event['show_ends_at_utc']->setTimezone($appointmentType->organization->timezone);
                $ticketSeats = $event === null
                    ? []
                    : ($slot->appointment === null
                        ? $ticketInventory->reserveForType($appointmentType, (int) $data['attendee_count'])
                        : $ticketInventory->reserveForAppointment($slot->appointment, (int) $data['attendee_count']));
                $slotPrice = $pricing->priceForBooking(
                    $appointmentType,
                    $duration,
                    $appointmentType->duration_unit,
                    (int) $data['attendee_count'],
                    $ticketSeats,
                );
                if ($equipmentTotal > PHP_INT_MAX - $slotPrice) {
                    throw new \InvalidArgumentException('The appointment price is too large.');
                }
                $slotPrice += $equipmentTotal;
                if ($depositTotal > PHP_INT_MAX - $slotPrice) {
                    throw new \InvalidArgumentException('The appointment price is too large.');
                }
                $slotPrice += $depositTotal;

                return [
                    'starts_at_utc' => $slot->startsAtUtc->toIso8601String(),
                    'ends_at_utc' => $slot->endsAtUtc->toIso8601String(),
                    'client_label' => $clientStart->format('g:i A').' – '.$clientEnd->format('g:i A'),
                    'organization_label' => $orgStart->format('g:i A').' – '.$orgEnd->format('g:i A'),
                    'client_event_label' => $event === null ? null : 'Doors '.$clientStart->format('g:i A').' · Show '.$clientShowStart->format('g:i A').($clientShowEnd ? ' – '.$clientShowEnd->format('g:i A') : ''),
                    'organization_event_label' => $event === null ? null : 'Doors '.$orgStart->format('g:i A').' · Show '.$orgShowStart->format('g:i A').($orgShowEnd ? ' – '.$orgShowEnd->format('g:i A') : ''),
                    'remaining_capacity' => $slot->remainingCapacity,
                    'equipment_availability' => $slot->equipmentAvailability,
                    'join_existing' => $slot->appointment !== null,
                    'price_minor' => $slotPrice,
                    'price_display' => $money->format($slotPrice, $appointmentType->organization->currency),
                ];
            }, $slots);
        } catch (RuntimeException|\InvalidArgumentException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json([
            'timezone' => $timezone,
            'organization_timezone' => $appointmentType->organization->timezone,
            'price_minor' => $price,
            'price_display' => $money->format($price, $appointmentType->organization->currency),
            'ticketed_event' => (bool) $appointmentType->ticketing_enabled,
            'slots' => $slotPayloads,
        ]);
    }

    public function hold(
        Request $request,
        AppointmentType $appointmentType,
        PublicAppointmentAccessService $access,
        PublicBookingHoldService $holds,
    ): JsonResponse {
        $data = $request->validate([
            'access_mode' => ['required', Rule::in(['direct', 'unlisted', 'invitation'])],
            'access_token' => ['nullable', 'string', 'max:128'],
            'timezone' => ['required', 'string', new IanaTimezone],
            'starts_at_utc' => ['required', 'date'],
            'duration_value' => ['nullable', 'integer', 'min:1'],
            'attendee_count' => ['required', 'integer', 'min:1'],
        ]);

        $invitation = $access->resolve($appointmentType, $request, $data['access_mode'], $data['access_token'] ?? null);
        $this->validateAttendeeCount($appointmentType, (int) $data['attendee_count']);

        try {
            $lease = $holds->acquire(
                $appointmentType->loadMissing(['organization', 'contractTemplate']),
                CarbonImmutable::parse($data['starts_at_utc'])->utc(),
                isset($data['duration_value']) ? (int) $data['duration_value'] : null,
                $data['timezone'],
                (int) $data['attendee_count'],
                $invitation,
            );
        } catch (RuntimeException|\InvalidArgumentException $exception) {
            return response()->json(['message' => $exception->getMessage()], 409);
        }

        return response()->json([
            'continue_url' => route('public.booking-holds.edit', $lease->token),
            'expires_at_utc' => $lease->hold->expires_at_utc->toIso8601String(),
        ]);
    }

    public function editHold(
        string $token,
        TicketEventService $ticketEvents,
        ConditionalResourceRequirementService $conditionalResources,
    ): View
    {
        $hold = $this->holdByToken($token);
        $hold->load(['organization', 'resources', 'appointmentType.organization', 'appointmentType.resources', 'appointmentType.questions.options', 'appointmentType.questions.visibilityConditions.sourceQuestion', 'appointmentType.questions.visibilityConditions.expectedOption', 'appointmentType.questions.visibilityConditions.expectedOptions', 'appointmentType.shortNoticeFeeRules', 'contractTemplate', 'invitation']);

        return view('public.bookings.details', [
            'organization' => $hold->organization,
            'type' => $hold->appointmentType,
            'hold' => $hold,
            'holdToken' => $token,
            'resourceUnavailableDefaults' => $conditionalResources->unavailableDefaultAnswers($hold),
            'eventTiming' => $hold->appointmentType->ticketing_enabled
                ? $ticketEvents->appointmentAttributes(
                    $hold->appointmentType,
                    CarbonImmutable::instance($hold->starts_at_utc)->utc(),
                    CarbonImmutable::instance($hold->ends_at_utc)->utc(),
                )
                : null,
        ]);
    }

    public function quote(
        Request $request,
        string $token,
        QuestionnaireSubmissionService $questionnaires,
        MoneyService $money,
        CouponRedemptionService $coupons,
        ConditionalResourceRequirementService $conditionalResources,
    ): JsonResponse {
        $hold = $this->holdByToken($token);
        $hold->load(['resources', 'appointmentType.organization', 'appointmentType.resources', 'appointmentType.questions.options', 'appointmentType.questions.visibilityConditions.sourceQuestion', 'appointmentType.questions.visibilityConditions.expectedOption', 'appointmentType.questions.visibilityConditions.expectedOptions', 'appointmentType.shortNoticeFeeRules']);
        $answers = (array) $request->input('answers', []);
        try {
            $quote = $questionnaires->quote(
                $hold->appointmentType,
                (int) $hold->duration_value,
                $answers,
                CarbonImmutable::instance($hold->starts_at_utc)->utc(),
                attendeeCount: (int) $hold->attendee_count,
                ticketSeats: $hold->ticket_seats ?? [],
                equipmentResourceQuantities: $hold->resources->mapWithKeys(fn ($resource) => [
                    $resource->getKey() => (int) ($resource->pivot->quantity_reserved ?? 1),
                ])->all(),
                forcedAnswers: $conditionalResources->unavailableDefaultAnswers($hold),
            );
            if (trim((string) $request->input('coupon_code')) !== '') {
                $quote = $coupons->apply(
                    (string) $request->input('coupon_code'),
                    $hold->appointmentType,
                    new QuestionnaireSubmission([], $quote),
                )->submission->quote;
            }
        } catch (\InvalidArgumentException|RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }
        return response()->json([
            'base_price_minor' => $quote->basePriceMinor,
            'total_minor' => $quote->totalMinor,
            'total_display' => $money->format($quote->totalMinor, $hold->organization->currency),
            'lines' => array_map(fn ($line) => [
                'label' => $line->label,
                'quantity' => $line->quantity,
                'amount_minor' => $line->amountMinor,
                'amount_display' => ($line->lineType === 'coupon_discount' ? '−' : '').$money->format($line->amountMinor, $hold->organization->currency),
            ], $quote->lines),
        ]);
    }

    public function store(
        Request $request,
        string $token,
        BookingCreationService $bookings,
        QuestionnaireSubmissionService $questionnaires,
        ConditionalResourceRequirementService $conditionalResources,
    ): RedirectResponse {
        $hold = $this->holdByToken($token);
        $hold->load(['resources', 'appointmentType.organization', 'appointmentType.resources', 'appointmentType.questions.options', 'appointmentType.questions.visibilityConditions.sourceQuestion', 'appointmentType.questions.visibilityConditions.expectedOption', 'appointmentType.questions.visibilityConditions.expectedOptions', 'appointmentType.shortNoticeFeeRules', 'contractTemplate', 'invitation']);

        $rules = [
            'first_name' => ['required', 'string', 'max:120'],
            'last_name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email:rfc', 'max:254'],
            'phone' => ['nullable', 'string', 'max:64'],
            'attendees' => ['nullable', 'array', 'max:'.max(0, (int) $hold->attendee_count - 1)],
            'attendees.*.first_name' => ['nullable', 'string', 'max:120'],
            'attendees.*.last_name' => ['nullable', 'string', 'max:120'],
            'attendees.*.email' => ['nullable', 'email:rfc', 'max:254'],
            'coupon_code' => ['nullable', 'string', 'max:80'],
        ];

        if ($hold->contract_template_id !== null) {
            $rules['contract_files'] = ['required', 'array', 'min:1', 'max:'.config('contracts.max_signed_files', 30)];
            $rules['contract_files.*'] = [
                'required', 'file',
                'mimes:'.implode(',', config('contracts.signed_extensions', ['pdf', 'jpg', 'jpeg', 'png', 'webp'])),
                'max:'.config('contracts.max_signed_file_kilobytes', 20480),
            ];
        }

        $data = $request->validate($rules);
        $questionnaire = $questionnaires->validateForBooking(
            $request,
            $hold->appointmentType,
            (int) $hold->duration_value,
            CarbonImmutable::instance($hold->starts_at_utc)->utc(),
            attendeeCount: (int) $hold->attendee_count,
            ticketSeats: $hold->ticket_seats ?? [],
            equipmentResourceQuantities: $hold->resources->mapWithKeys(fn ($resource) => [
                $resource->getKey() => (int) ($resource->pivot->quantity_reserved ?? 1),
            ])->all(),
            forcedAnswers: $conditionalResources->unavailableDefaultAnswers($hold),
        );
        $files = array_values($request->file('contract_files', []));
        $this->validateContractSet($files);

        try {
            $result = $bookings->createFromHold(
                $token,
                [
                    'first_name' => $data['first_name'],
                    'last_name' => $data['last_name'],
                    'email' => $data['email'],
                    'phone' => $data['phone'] ?? null,
                ],
                array_values($data['attendees'] ?? []),
                $files,
                $questionnaire,
                $data['coupon_code'] ?? null,
            );
        } catch (RuntimeException $exception) {
            return back()->withInput()->withErrors(['booking' => $exception->getMessage()]);
        }

        $booking = $result->booking;
        if ($result->emailVerificationToken !== null) {
            Notification::route('mail', $booking->email)->notify(new VerifyBookingEmail($booking, $result->emailVerificationToken));
        } else {
            Notification::route('mail', $booking->email)->notify(new BookingAccessEmail($booking, $result->manageToken));
        }

        if ($booking->status->value === 'confirmed' && $booking->appointmentType->redirect_url) {
            return redirect()->away($booking->appointmentType->redirect_url);
        }

        $response = redirect()->route('public.bookings.received', $booking->reference);
        if ($booking->status->value !== 'pending_email_verification') {
            $response->with('manage_url', route('public.bookings.manage', [$booking, $result->manageToken]));
        }

        return $response;
    }

    public function contract(string $token): StreamedResponse
    {
        $hold = $this->holdByToken($token);
        $template = $hold->contractTemplate()->firstOrFail();
        abort_unless(Storage::disk($template->disk)->exists($template->path), 404);

        return Storage::disk($template->disk)->download($template->path, $template->original_name);
    }

    public function received(string $reference): View
    {
        $booking = \App\Models\Booking::query()
            ->with(['organization', 'appointmentType'])
            ->where('reference', $reference)
            ->firstOrFail();

        $maskedEmail = $this->maskEmail($booking->email);

        return view('public.bookings.received', compact('booking', 'maskedEmail') + ['organization' => $booking->organization]);
    }


    private function maskEmail(string $email): string
    {
        [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');
        if ($domain === '') {
            return 'your email address';
        }

        $visible = substr($local, 0, min(2, strlen($local)));
        return $visible.str_repeat('•', max(2, strlen($local) - strlen($visible))).'@'.$domain;
    }

    private function holdByToken(string $token): BookingHold
    {
        $hold = BookingHold::query()
            ->where('token_hash', hash('sha256', $token, true))
            ->firstOrFail();

        abort_unless($hold->isActive(), 410, 'This booking hold has expired.');

        return $hold;
    }

    private function validateAttendeeCount(AppointmentType $type, int $count): void
    {
        if ($type->attendance_mode === AttendanceMode::Single && $count !== 1) {
            abort(422, 'This appointment accepts one attendee.');
        }

        if ($count < 1 || $count > (int) $type->capacity) {
            abort(422, 'The attendee count exceeds this appointment capacity.');
        }
    }

    private function validateContractSet(array $files): void
    {
        if ($files === []) {
            return;
        }

        $pdfCount = collect($files)->filter(fn ($file) => strtolower($file->getClientOriginalExtension()) === 'pdf')->count();
        if ($pdfCount > 0 && count($files) !== 1) {
            throw ValidationException::withMessages(['contract_files' => 'Upload either one PDF or one or more page images, not a mixture.']);
        }
    }
}
