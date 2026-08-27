<?php

namespace App\Domain\Bookings;

use App\Domain\Availability\AppointmentDurationService;
use App\Domain\Availability\AvailabilityService;
use App\Domain\Availability\BookableSlot;
use App\Domain\Availability\OrganizationHolidayService;
use App\Enums\AppointmentStatus;
use App\Enums\AttendanceMode;
use App\Enums\BookingHoldStatus;
use App\Enums\BookingStatus;
use App\Models\Appointment;
use App\Models\AppointmentType;
use Carbon\CarbonImmutable;

class PublicBookingAvailabilityService
{
    public function __construct(
        private readonly AvailabilityService $availability,
        private readonly AppointmentDurationService $durations,
        private readonly BookingNoticeService $notice,
        private readonly OrganizationHolidayService $holidays,
    ) {
    }

    /** @return list<BookableSlot> */
    public function slots(
        AppointmentType $type,
        CarbonImmutable $rangeStartUtc,
        CarbonImmutable $rangeEndUtc,
        ?int $durationValue,
        string $bookingTimezone,
        int $attendeeCount,
        bool $enforceNotice = true,
    ): array {
        $type->loadMissing('organization');
        $selectedDuration = $this->durations->selectedValue($type, $durationValue);
        $result = [];
        $nowUtc = CarbonImmutable::now('UTC');

        foreach ($this->availability->slots($type, $rangeStartUtc, $rangeEndUtc, $selectedDuration, $bookingTimezone) as $slot) {
            if ($enforceNotice && ! $this->notice->permits($type, $slot->startsAtUtc, $nowUtc)) {
                continue;
            }
            $result[$slot->startsAtUtc->format('Y-m-d\TH:i:s.u\Z')] = new BookableSlot(
                $slot->startsAtUtc,
                $slot->endsAtUtc,
                null,
                (int) $type->capacity,
            );
        }

        if ($type->attendance_mode !== AttendanceMode::Group) {
            ksort($result);
            return array_values($result);
        }

        $appointments = Appointment::query()
            ->with(['bookings', 'bookingHolds'])
            ->where('appointment_type_id', $type->getKey())
            ->where('status', AppointmentStatus::Scheduled->value)
            ->where('duration_value', $selectedDuration)
            ->where('starts_at_utc', '>=', $rangeStartUtc->format('Y-m-d H:i:s.u'))
            ->where('starts_at_utc', '<', $rangeEndUtc->format('Y-m-d H:i:s.u'))
            ->orderBy('starts_at_utc')
            ->get();

        foreach ($appointments as $appointment) {
            $remaining = $this->remainingCapacity($appointment);
            if ($remaining < $attendeeCount) {
                continue;
            }

            $start = CarbonImmutable::instance($appointment->starts_at_utc)->utc();
            if ($enforceNotice && ! $this->notice->permits($type, $start, $nowUtc)) {
                continue;
            }
            $end = CarbonImmutable::instance($appointment->ends_at_utc)->utc();
            if ($this->holidays->isClosed($type->organization, $start, $end)) {
                continue;
            }
            $result[$start->format('Y-m-d\TH:i:s.u\Z')] = new BookableSlot($start, $end, $appointment, $remaining);
        }

        ksort($result);
        return array_values($result);
    }

    public function remainingCapacity(Appointment $appointment): int
    {
        $booked = $appointment->bookings
            ->filter(fn ($booking): bool => $booking->status instanceof BookingStatus
                ? $booking->status->occupiesCapacity()
                : BookingStatus::from((string) $booking->status)->occupiesCapacity())
            ->sum('attendee_count');

        $held = $appointment->bookingHolds
            ->filter(fn ($hold): bool => $hold->status === BookingHoldStatus::Active && $hold->expires_at_utc?->isFuture())
            ->sum('attendee_count');

        return max(0, (int) $appointment->capacity - (int) $booked - (int) $held);
    }
}
