<?php

namespace App\Domain\Bookings;

use App\Domain\Availability\AppointmentDurationService;
use App\Domain\Availability\BookingHoldLease;
use App\Domain\Availability\BookingHoldService;
use App\Domain\Availability\OrganizationHolidayService;
use App\Enums\AppointmentStatus;
use App\Enums\AttendanceMode;
use App\Enums\BookingHoldStatus;
use App\Enums\BookingStatus;
use App\Models\Appointment;
use App\Models\AppointmentContractTemplate;
use App\Models\AppointmentType;
use App\Models\AppointmentTypeInvitation;
use App\Models\BookingHold;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class PublicBookingHoldService
{
    public function __construct(
        private readonly BookingHoldService $holds,
        private readonly AppointmentDurationService $durations,
        private readonly BookingNoticeService $notice,
        private readonly OrganizationHolidayService $holidays,
    ) {
    }

    public function acquire(
        AppointmentType $type,
        CarbonImmutable $startsAtUtc,
        ?int $durationValue,
        string $bookingTimezone,
        int $attendeeCount,
        ?AppointmentTypeInvitation $invitation = null,
        bool $enforceNotice = true,
        ?int $ttlMinutes = null,
    ): BookingHoldLease {
        $type->loadMissing('organization');
        if ($enforceNotice && ($message = $this->notice->failureMessage($type, $startsAtUtc)) !== null) {
            throw new RuntimeException($message);
        }

        $capacity = (int) $type->capacity;
        if ($attendeeCount < 1 || $attendeeCount > $capacity) {
            throw new RuntimeException('The requested attendee count exceeds this appointment capacity.');
        }

        $selectedDuration = $this->durations->selectedValue($type, $durationValue);
        $endsAtUtc = $this->durations->endAt($startsAtUtc, $type, $selectedDuration, $bookingTimezone);
        if ($this->holidays->isClosed($type->organization, $startsAtUtc, $endsAtUtc)) {
            throw new RuntimeException('The organization is closed on the selected date.');
        }

        if ($type->attendance_mode === AttendanceMode::Group) {
            $existing = Appointment::query()
                ->where('appointment_type_id', $type->getKey())
                ->where('status', AppointmentStatus::Scheduled->value)
                ->where('duration_value', $selectedDuration)
                ->where('starts_at_utc', $startsAtUtc->format('Y-m-d H:i:s.u'))
                ->first();

            if ($existing !== null) {
                return $this->acquireCapacityHold($type, $existing, $bookingTimezone, $attendeeCount, $invitation, $ttlMinutes);
            }
        }

        $lease = $this->holds->acquire(
            $type,
            $startsAtUtc,
            $selectedDuration,
            $bookingTimezone,
            $ttlMinutes ?? (int) config('booking.public_hold_ttl_minutes', 15),
        );

        $contractId = $type->contractTemplate()->value('id');
        $lease->hold->update([
            'appointment_type_invitation_id' => $invitation?->getKey(),
            'contract_template_id' => $contractId,
            'attendee_count' => $attendeeCount,
        ]);

        return new BookingHoldLease($lease->hold->fresh(['resources', 'contractTemplate', 'invitation']), $lease->token);
    }

    private function acquireCapacityHold(
        AppointmentType $type,
        Appointment $appointment,
        string $bookingTimezone,
        int $attendeeCount,
        ?AppointmentTypeInvitation $invitation,
        ?int $ttlMinutes,
    ): BookingHoldLease {
        return DB::transaction(function () use ($type, $appointment, $bookingTimezone, $attendeeCount, $invitation, $ttlMinutes): BookingHoldLease {
            $locked = Appointment::query()->whereKey($appointment->getKey())->lockForUpdate()->firstOrFail();
            abort_unless($locked->status === AppointmentStatus::Scheduled, 409);

            $booked = $locked->bookings()
                ->whereNotIn('status', [BookingStatus::Cancelled->value, BookingStatus::Declined->value])
                ->sum('attendee_count');
            $held = $locked->bookingHolds()
                ->where('status', BookingHoldStatus::Active->value)
                ->where('expires_at_utc', '>', now('UTC'))
                ->sum('attendee_count');

            if (((int) $booked + (int) $held + $attendeeCount) > (int) $locked->capacity) {
                throw new RuntimeException('This group appointment no longer has enough remaining capacity.');
            }

            $token = Str::random(64);
            $contractId = $type->contractTemplate()->value('id');
            $hold = BookingHold::create([
                'organization_id' => $type->organization_id,
                'appointment_type_id' => $type->getKey(),
                'appointment_id' => $locked->getKey(),
                'appointment_type_invitation_id' => $invitation?->getKey(),
                'contract_template_id' => $contractId,
                'token_hash' => hash('sha256', $token, true),
                'starts_at_utc' => $locked->starts_at_utc,
                'ends_at_utc' => $locked->ends_at_utc,
                'blocked_starts_at_utc' => $locked->blocked_starts_at_utc,
                'blocked_ends_at_utc' => $locked->blocked_ends_at_utc,
                'booking_timezone' => $bookingTimezone,
                'duration_value' => $locked->duration_value,
                'attendee_count' => $attendeeCount,
                'status' => BookingHoldStatus::Active->value,
                'expires_at_utc' => now('UTC')->addMinutes($ttlMinutes ?? (int) config('booking.public_hold_ttl_minutes', 15)),
            ]);

            return new BookingHoldLease($hold->fresh(['appointment', 'contractTemplate', 'invitation']), $token);
        }, 3);
    }
}
