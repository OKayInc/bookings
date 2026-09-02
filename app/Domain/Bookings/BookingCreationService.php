<?php

namespace App\Domain\Bookings;

use App\Domain\Appointments\AppointmentTypePricingService;
use App\Domain\Questionnaires\QuestionnairePersistenceService;
use App\Domain\Questionnaires\QuestionnairePricingService;
use App\Domain\Questionnaires\QuestionnaireSubmission;
use App\Domain\Calendars\CalendarSyncService;
use App\Domain\Calendars\CalendarAvailabilityService;
use App\Domain\Conferences\ConferenceMeetingService;
use App\Domain\Availability\AvailabilityInterval;
use App\Domain\Availability\AppointmentTypeSeasonService;
use App\Domain\Availability\OrganizationHolidayService;
use App\Domain\Availability\ResourceHolidayService;
use App\Domain\Tickets\TicketAllocationService;
use App\Domain\Tickets\TicketEventService;
use App\Domain\Tickets\TicketInventoryService;
use App\Domain\Payments\PaymentRuleService;
use App\Domain\Payments\BookingPaymentSnapshotService;
use App\Enums\AppointmentStatus;
use App\Enums\BookingHoldStatus;
use App\Enums\BookingStatus;
use App\Enums\EmailVerificationMode;
use App\Models\Appointment;
use App\Models\AppointmentTypeInvitation;
use App\Models\Booking;
use App\Models\BookingHold;
use App\Models\OrganizationContact;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class BookingCreationService
{
    public function __construct(
        private readonly AppointmentTypePricingService $pricing,
        private readonly ContractSubmissionService $contracts,
        private readonly BookingWorkflowService $workflow,
        private readonly QuestionnairePricingService $questionnairePricing,
        private readonly QuestionnairePersistenceService $questionnairePersistence,
        private readonly CalendarSyncService $calendarSync,
        private readonly ConferenceMeetingService $conferenceMeetings,
        private readonly CalendarAvailabilityService $externalCalendars,
        private readonly BookingResourceNotificationService $resourceNotifications,
        private readonly OrganizationHolidayService $holidays,
        private readonly ResourceHolidayService $resourceHolidays,
        private readonly AppointmentTypeSeasonService $seasons,
        private readonly TicketAllocationService $ticketAllocation,
        private readonly TicketEventService $ticketEvents,
        private readonly TicketInventoryService $ticketInventory,
        private readonly PaymentRuleService $paymentRules,
        private readonly BookingPaymentSnapshotService $paymentSnapshots,
    ) {
    }

    /**
     * @param array{first_name:string,last_name:string,email:string,phone?:?string} $contactData
     * @param list<array{first_name?:?string,last_name?:?string,email?:?string}> $additionalAttendees
     * @param list<UploadedFile> $contractFiles
     */
    public function createFromHold(
        string $holdToken,
        array $contactData,
        array $additionalAttendees = [],
        array $contractFiles = [],
        ?QuestionnaireSubmission $questionnaire = null,
    ): BookingCreationResult {
        $emailVerificationToken = null;
        $manageToken = Str::random(64);

        $booking = DB::transaction(function () use (
            $holdToken,
            $contactData,
            $additionalAttendees,
            $contractFiles,
            &$emailVerificationToken,
            $manageToken,
            $questionnaire,
        ): Booking {
            $hold = BookingHold::query()
                ->where('token_hash', hash('sha256', $holdToken, true))
                ->lockForUpdate()
                ->first();

            if ($hold === null || ! $hold->isActive()) {
                throw new RuntimeException('This booking hold has expired. Please choose the appointment time again.');
            }

            $hold->load(['appointmentType.organization', 'resources', 'invitation', 'contractTemplate']);
            $type = $hold->appointmentType;
            $organization = $type->organization;
            if (! $this->seasons->contains(
                $type,
                CarbonImmutable::instance($hold->starts_at_utc)->utc(),
                CarbonImmutable::instance($hold->ends_at_utc)->utc(),
            )) {
                throw new RuntimeException('This appointment type is no longer offered during the selected dates. Please choose another time.');
            }
            if ($this->holidays->isClosed(
                $organization,
                CarbonImmutable::instance($hold->starts_at_utc)->utc(),
                CarbonImmutable::instance($hold->ends_at_utc)->utc(),
            )) {
                throw new RuntimeException('The organization is now closed on this date. Please choose another time.');
            }
            $holidayResources = $hold->resources;
            if ($holidayResources->isEmpty() && $hold->appointment_id !== null) {
                $holidayResources = Appointment::query()->whereKey($hold->appointment_id)->firstOrFail()->resources()->get();
            }
            if ($this->resourceHolidays->assignedRequiredResourcesClosed(
                $organization,
                $holidayResources,
                CarbonImmutable::instance($hold->starts_at_utc)->utc(),
                CarbonImmutable::instance($hold->ends_at_utc)->utc(),
            )) {
                throw new RuntimeException('A required resource is now closed for a holiday on this date. Please choose another time.');
            }
            $email = trim($contactData['email']);
            $normalized = OrganizationContact::normalizeEmail($email);
            $paymentRule = $this->paymentRules->assertMayBook($organization, $email);

            if ($hold->invitation !== null) {
                /** @var AppointmentTypeInvitation $invitation */
                $invitation = AppointmentTypeInvitation::query()
                    ->whereKey($hold->invitation->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                if (! $invitation->isUsable()) {
                    throw new RuntimeException('This invitation is no longer available.');
                }

                if ($invitation->recipient_email !== null
                    && ! hash_equals(OrganizationContact::normalizeEmail($invitation->recipient_email), $normalized)) {
                    throw new RuntimeException('This invitation was issued to a different email address.');
                }
            }

            if ($hold->appointment_id === null) {
                $blocked = new AvailabilityInterval(
                    \Carbon\CarbonImmutable::instance($hold->blocked_starts_at_utc)->utc(),
                    \Carbon\CarbonImmutable::instance($hold->blocked_ends_at_utc)->utc(),
                );
                if ($this->externalCalendars->assignedRequiredResourcesBusy(
                    $type,
                    $hold->resources,
                    $blocked->start,
                    $blocked->end,
                    true,
                )) {
                    throw new RuntimeException('This time became busy in a connected calendar while you were completing the booking. Please choose another time.');
                }
            }

            $contact = OrganizationContact::query()
                ->where('organization_id', $organization->getKey())
                ->where('email_normalized', $normalized)
                ->lockForUpdate()
                ->first();

            if ($contact === null) {
                $contact = OrganizationContact::create([
                    'organization_id' => $organization->getKey(),
                    'first_name' => $contactData['first_name'],
                    'last_name' => $contactData['last_name'],
                    'email' => $email,
                    'phone' => $contactData['phone'] ?? null,
                ]);
            } else {
                $contact->update([
                    'first_name' => $contactData['first_name'],
                    'last_name' => $contactData['last_name'],
                    'email' => $email,
                    'phone' => $contactData['phone'] ?? $contact->phone,
                ]);
            }

            $appointment = $this->appointmentForHold($hold, $type->capacity);
            $this->assertCapacity($appointment, (int) $hold->attendee_count);
            $ticketSeats = [];
            if ($appointment->ticketing_enabled) {
                $ticketSeats = empty($hold->ticket_seats)
                    ? $this->ticketInventory->reserveForAppointment($appointment, (int) $hold->attendee_count, $hold)
                    : $this->ticketInventory->validateReservation(
                        $appointment,
                        $hold->ticket_seats,
                        (int) $hold->attendee_count,
                        $hold,
                    );
            }

            $basePriceMinor = $this->pricing->priceForBooking(
                $type,
                (int) $hold->duration_value,
                $type->duration_unit,
                (int) $hold->attendee_count,
                $ticketSeats,
            );
            $questionnaire ??= new QuestionnaireSubmission([], $this->questionnairePricing->quote(
                $type,
                (int) $hold->duration_value,
                [],
                CarbonImmutable::instance($hold->starts_at_utc)->utc(),
                attendeeCount: (int) $hold->attendee_count,
                ticketSeats: $ticketSeats,
                equipmentResourceQuantities: $hold->resources->mapWithKeys(fn ($resource) => [
                    $resource->getKey() => (int) ($resource->pivot->quantity_reserved ?? 1),
                ])->all(),
            ));
            if ($questionnaire->quote->basePriceMinor !== $basePriceMinor) {
                throw new RuntimeException('The appointment price has changed. Please review the price and submit the booking again.');
            }
            $priceMinor = $questionnaire->quote->totalMinor;
            $paymentSnapshot = $this->paymentSnapshots->snapshot(
                $type,
                $priceMinor,
                CarbonImmutable::instance($hold->starts_at_utc)->utc(),
                $paymentRule !== null,
                $paymentRule?->getKey(),
            );
            $requiresVerification = $type->email_verification_mode !== EmailVerificationMode::None
                && $contact->email_verified_at === null;

            if ($requiresVerification) {
                $emailVerificationToken = Str::random(64);
            }

            $booking = Booking::create([
                'organization_id' => $organization->getKey(),
                'appointment_id' => $appointment->getKey(),
                'appointment_type_id' => $type->getKey(),
                'organization_contact_id' => $contact->getKey(),
                'appointment_type_invitation_id' => $hold->appointment_type_invitation_id,
                'contract_template_id' => $hold->contract_template_id,
                'reference' => $this->uniqueReference(),
                'status' => BookingStatus::PendingEmailVerification->value,
                'attendee_count' => (int) $hold->attendee_count,
                'booking_timezone' => $hold->booking_timezone,
                'base_price_minor' => $basePriceMinor,
                'price_minor' => $priceMinor,
                'currency' => $organization->currency,
                ...$paymentSnapshot,
                'first_name' => $contactData['first_name'],
                'last_name' => $contactData['last_name'],
                'email' => $email,
                'email_normalized' => $normalized,
                'phone' => $contactData['phone'] ?? null,
                'email_verified_at' => $contact->email_verified_at,
                'email_verification_token_hash' => $emailVerificationToken === null ? null : hash('sha256', $emailVerificationToken, true),
                'email_verification_expires_at_utc' => $emailVerificationToken === null
                    ? null
                    : now('UTC')->addHours((int) config('booking.email_verification_ttl_hours', 24)),
                'manage_token_hash' => hash('sha256', $manageToken, true),
                'requires_resource_confirmation' => (bool) $type->requires_resource_confirmation,
                'expires_at_utc' => $emailVerificationToken === null
                    ? null
                    : now('UTC')->addHours((int) config('booking.email_verification_ttl_hours', 24)),
                'cancellation_allowed' => (bool) ($type->cancellation_allowed ?? true),
                'cancellation_notice_value' => (int) ($type->cancellation_notice_value ?? 24),
                'cancellation_notice_unit' => $type->cancellation_notice_unit?->value ?? 'hour',
                'cancellation_policy_text' => $type->cancellation_policy_text,
                'rescheduling_allowed' => (bool) ($type->rescheduling_allowed ?? true),
                'rescheduling_notice_value' => (int) ($type->rescheduling_notice_value ?? 24),
                'rescheduling_notice_unit' => $type->rescheduling_notice_unit?->value ?? 'hour',
                'rescheduling_max_count' => (int) ($type->rescheduling_max_count ?? 0),
                'reschedule_count' => 0,
                'rescheduling_policy_text' => $type->rescheduling_policy_text,
            ]);

            $booking->attendees()->create([
                'position' => 1,
                'is_primary' => true,
                'first_name' => $booking->first_name,
                'last_name' => $booking->last_name,
                'email' => $booking->email,
            ]);

            for ($position = 2; $position <= (int) $hold->attendee_count; $position++) {
                $input = $additionalAttendees[$position - 2] ?? [];
                $booking->attendees()->create([
                    'position' => $position,
                    'is_primary' => false,
                    'first_name' => $input['first_name'] ?? null,
                    'last_name' => $input['last_name'] ?? null,
                    'email' => isset($input['email']) && $input['email'] !== '' ? trim((string) $input['email']) : null,
                ]);
            }

            $this->ticketAllocation->createForBooking(
                $booking->load(['appointment', 'attendees']),
                $ticketSeats,
                $hold,
            );

            $this->questionnairePersistence->persist($booking->load('organization'), $questionnaire);

            if ($hold->appointment_type_invitation_id !== null) {
                $invitation = AppointmentTypeInvitation::query()
                    ->whereKey($hold->appointment_type_invitation_id)
                    ->lockForUpdate()
                    ->firstOrFail();
                if (! $invitation->isUsable()) {
                    throw new RuntimeException('This invitation was used while you were completing the booking.');
                }
                $invitation->increment('uses_count');
            }

            if ($hold->contract_template_id !== null) {
                if ($contractFiles === []) {
                    throw new RuntimeException('A signed contract must be uploaded to complete this booking.');
                }
                $this->contracts->submit($booking->load('organization'), $contractFiles);
            }

            $hold->update(['status' => BookingHoldStatus::Consumed->value]);
            $this->workflow->refreshStatus($booking->load(['appointmentType', 'contractSubmissions']));

            return $booking->fresh(['appointment', 'appointmentType', 'organization', 'contact', 'attendees', 'answers.files', 'priceLines', 'contractSubmissions.files']);
        }, 3);

        $this->conferenceMeetings->safeSync($booking->appointment);
        $this->calendarSync->safeSyncAppointment($booking->appointment);
        $this->resourceNotifications->safeNotifyBookingCreated($booking);

        return new BookingCreationResult($booking, $emailVerificationToken, $manageToken);
    }

    private function appointmentForHold(BookingHold $hold, int $capacity): Appointment
    {
        if ($hold->appointment_id !== null) {
            return Appointment::query()->whereKey($hold->appointment_id)->lockForUpdate()->firstOrFail();
        }

        $startsAtUtc = CarbonImmutable::instance($hold->starts_at_utc)->utc();
        $endsAtUtc = CarbonImmutable::instance($hold->ends_at_utc)->utc();
        $appointment = Appointment::create([
            'organization_id' => $hold->organization_id,
            'appointment_type_id' => $hold->appointment_type_id,
            'starts_at_utc' => $hold->starts_at_utc,
            'ends_at_utc' => $hold->ends_at_utc,
            'blocked_starts_at_utc' => $hold->blocked_starts_at_utc,
            'blocked_ends_at_utc' => $hold->blocked_ends_at_utc,
            'scheduling_timezone' => $hold->booking_timezone,
            'duration_value' => $hold->duration_value,
            'capacity' => $capacity,
            'status' => AppointmentStatus::Scheduled->value,
            'meeting_provider' => $hold->appointmentType->is_online ? $hold->appointmentType->meeting_provider?->value : null,
            'meeting_status' => $hold->appointmentType->is_online ? 'pending' : null,
            ...$this->ticketEvents->appointmentAttributes($hold->appointmentType, $startsAtUtc, $endsAtUtc),
        ]);
        $appointment->resources()->sync(
            $hold->resources->mapWithKeys(fn ($resource) => [
                $resource->getKey() => [
                    'is_required' => (bool) $resource->pivot->is_required,
                    'replacement_group' => $resource->pivot->replacement_group,
                    'quantity_reserved' => (int) ($resource->pivot->quantity_reserved ?? 1),
                ],
            ])->all(),
        );
        $hold->update(['appointment_id' => $appointment->getKey()]);

        return $appointment;
    }

    private function assertCapacity(Appointment $appointment, int $requested): void
    {
        $booked = $appointment->bookings()
            ->whereNotIn('status', [BookingStatus::Cancelled->value, BookingStatus::Declined->value])
            ->sum('attendee_count');

        if (((int) $booked + $requested) > (int) $appointment->capacity) {
            throw new RuntimeException('This appointment no longer has enough remaining capacity.');
        }
    }

    private function uniqueReference(): string
    {
        $length = max(8, (int) config('booking.reference_length', 12));
        do {
            $reference = Str::upper(Str::random($length));
        } while (Booking::query()->where('reference', $reference)->exists());

        return $reference;
    }
}
