<?php

namespace App\Domain\Bookings;

use App\Enums\AppointmentStatus;
use App\Domain\Calendars\CalendarSyncService;
use App\Domain\Calendars\CalendarAvailabilityService;
use App\Domain\Conferences\ConferenceMeetingService;
use App\Domain\Availability\AvailabilityInterval;
use App\Domain\Availability\AppointmentTypeSeasonService;
use App\Domain\Availability\OrganizationHolidayService;
use App\Domain\Availability\ResourceHolidayService;
use App\Enums\BookingHoldStatus;
use App\Enums\BookingStatus;
use App\Models\Appointment;
use App\Models\Booking;
use App\Models\BookingHold;
use App\Models\BookingReschedule;
use App\Models\Person;
use Closure;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use App\Notifications\BookingStatusChangedEmail;
use RuntimeException;

class BookingRescheduleService
{
    public function __construct(
        private readonly BookingPolicyService $policy,
        private readonly ResourceConfirmationService $confirmations,
        private readonly BookingWorkflowService $workflow,
        private readonly AppointmentLifecycleService $lifecycle,
        private readonly CalendarSyncService $calendarSync,
        private readonly ConferenceMeetingService $conferenceMeetings,
        private readonly CalendarAvailabilityService $externalCalendars,
        private readonly OrganizationHolidayService $holidays,
        private readonly ResourceHolidayService $resourceHolidays,
        private readonly AppointmentTypeSeasonService $seasons,
    ) {
    }

    public function applyFromHold(
        Booking $booking,
        string $holdToken,
        bool $clientInitiated = true,
        ?Person $performedBy = null,
        ?string $reason = null,
    ): Booking {
        return $this->apply(
            $booking,
            fn (): BookingHold => BookingHold::query()
                ->where('token_hash', hash('sha256', $holdToken, true))
                ->lockForUpdate()
                ->firstOrFail(),
            $clientInitiated,
            $performedBy,
            $reason,
        );
    }

    public function applyFromReservedHold(
        Booking $booking,
        BookingHold $reservedHold,
        bool $clientInitiated = false,
        ?Person $performedBy = null,
        ?string $reason = null,
    ): Booking {
        return $this->apply(
            $booking,
            fn (): BookingHold => BookingHold::query()
                ->whereKey($reservedHold->getKey())
                ->lockForUpdate()
                ->firstOrFail(),
            $clientInitiated,
            $performedBy,
            $reason,
        );
    }

    private function apply(
        Booking $booking,
        Closure $holdResolver,
        bool $clientInitiated,
        ?Person $performedBy,
        ?string $reason,
    ): Booking {
        if ($clientInitiated && ! $this->policy->canReschedule($booking)) {
            throw new RuntimeException($this->policy->reschedulingStatus($booking));
        }

        $oldAppointment = $booking->appointment;
        $updated = DB::transaction(function () use ($booking, $holdResolver, $clientInitiated, $performedBy, $reason): Booking {
            $lockedBooking = Booking::query()->whereKey($booking->getKey())->lockForUpdate()->firstOrFail();
            if (in_array($lockedBooking->status, [BookingStatus::Cancelled, BookingStatus::Declined], true)) {
                throw new RuntimeException('This booking can no longer be rescheduled.');
            }
            if ($clientInitiated && ! $this->policy->canReschedule($lockedBooking)) {
                throw new RuntimeException($this->policy->reschedulingStatus($lockedBooking));
            }

            $hold = $holdResolver();
            if ($hold->status !== BookingHoldStatus::Active || $hold->expires_at_utc?->isPast()) {
                throw new RuntimeException('The selected rescheduling hold has expired.');
            }
            if (! hash_equals($hold->appointment_type_id, $lockedBooking->appointment_type_id)
                || (int) $hold->attendee_count !== (int) $lockedBooking->attendee_count) {
                throw new RuntimeException('The selected hold does not match this booking.');
            }

            $lockedBooking->loadMissing('organization');
            if ($this->holidays->isClosed(
                $lockedBooking->organization,
                CarbonImmutable::instance($hold->starts_at_utc)->utc(),
                CarbonImmutable::instance($hold->ends_at_utc)->utc(),
            )) {
                throw new RuntimeException('The organization is now closed on the proposed date. Please choose another time.');
            }
            $hold->loadMissing(['resources', 'appointmentType.organization']);
            if (! $this->seasons->contains(
                $hold->appointmentType,
                CarbonImmutable::instance($hold->starts_at_utc)->utc(),
                CarbonImmutable::instance($hold->ends_at_utc)->utc(),
            )) {
                throw new RuntimeException('This appointment type is no longer offered during the proposed dates. Please choose another time.');
            }
            $holidayResources = $hold->resources;
            if ($holidayResources->isEmpty() && $hold->appointment_id !== null) {
                $holidayResources = Appointment::query()->whereKey($hold->appointment_id)->firstOrFail()->resources()->get();
            }
            if ($this->resourceHolidays->assignedRequiredResourcesClosed(
                $lockedBooking->organization,
                $holidayResources,
                CarbonImmutable::instance($hold->starts_at_utc)->utc(),
                CarbonImmutable::instance($hold->ends_at_utc)->utc(),
            )) {
                throw new RuntimeException('A required resource is now closed for a holiday on the proposed date. Please choose another time.');
            }

            if ($hold->appointment_id === null) {
                $hold->loadMissing('appointmentType');
                $blocked = new AvailabilityInterval(
                    \Carbon\CarbonImmutable::instance($hold->blocked_starts_at_utc)->utc(),
                    \Carbon\CarbonImmutable::instance($hold->blocked_ends_at_utc)->utc(),
                );
                if ($this->externalCalendars->assignedRequiredResourcesBusy(
                    $hold->appointmentType,
                    $hold->resources,
                    $blocked->start,
                    $blocked->end,
                    true,
                )) {
                    throw new RuntimeException('The proposed time became busy in a connected calendar. Please choose another time.');
                }
            }

            $from = Appointment::query()->whereKey($lockedBooking->appointment_id)->lockForUpdate()->firstOrFail();
            $to = $hold->appointment_id !== null
                ? Appointment::query()->whereKey($hold->appointment_id)->lockForUpdate()->firstOrFail()
                : $this->createAppointmentFromHold($hold);

            BookingReschedule::create([
                'booking_id' => $lockedBooking->getKey(),
                'from_appointment_id' => $from->getKey(),
                'to_appointment_id' => $to->getKey(),
                'performed_by_person_id' => $performedBy?->getKey(),
                'client_initiated' => $clientInitiated,
                'from_starts_at_utc' => $from->starts_at_utc,
                'from_ends_at_utc' => $from->ends_at_utc,
                'to_starts_at_utc' => $to->starts_at_utc,
                'to_ends_at_utc' => $to->ends_at_utc,
                'reason' => $reason ?: null,
            ]);

            $lockedBooking->update([
                'appointment_id' => $to->getKey(),
                'booking_timezone' => $hold->booking_timezone,
                'reschedule_count' => (int) $lockedBooking->reschedule_count + ($clientInitiated ? 1 : 0),
            ]);
            $hold->update(['status' => BookingHoldStatus::Consumed->value]);
            $this->confirmations->resetForReschedule($lockedBooking);

            return $lockedBooking->fresh(['appointment', 'appointmentType', 'organization', 'contractSubmissions']);
        }, 3);

        $this->lifecycle->cancelIfOrphaned($oldAppointment);
        $this->workflow->refreshStatus($updated);
        $updated = $updated->fresh(['appointment', 'appointmentType']);
        $this->conferenceMeetings->safeSync($updated->appointment);
        $this->calendarSync->safeSyncAppointment($updated->appointment);
        Notification::route('mail', $updated->email)->notify(new BookingStatusChangedEmail($updated, 'Your booking has been rescheduled.'));

        return $updated;
    }

    private function createAppointmentFromHold(BookingHold $hold): Appointment
    {
        $hold->loadMissing(['resources', 'appointmentType']);
        $appointment = Appointment::create([
            'organization_id' => $hold->organization_id,
            'appointment_type_id' => $hold->appointment_type_id,
            'starts_at_utc' => $hold->starts_at_utc,
            'ends_at_utc' => $hold->ends_at_utc,
            'blocked_starts_at_utc' => $hold->blocked_starts_at_utc,
            'blocked_ends_at_utc' => $hold->blocked_ends_at_utc,
            'scheduling_timezone' => $hold->booking_timezone,
            'duration_value' => $hold->duration_value,
            'capacity' => $hold->appointmentType()->value('capacity'),
            'status' => AppointmentStatus::Scheduled->value,
            'meeting_provider' => $hold->appointmentType->is_online ? $hold->appointmentType->meeting_provider?->value : null,
            'meeting_status' => $hold->appointmentType->is_online ? 'pending' : null,
        ]);
        $appointment->resources()->sync($hold->resources->mapWithKeys(fn ($resource) => [
            $resource->getKey() => [
                'is_required' => (bool) $resource->pivot->is_required,
                'replacement_group' => $resource->pivot->replacement_group,
            ],
        ])->all());
        $hold->update(['appointment_id' => $appointment->getKey()]);

        return $appointment;
    }
}
