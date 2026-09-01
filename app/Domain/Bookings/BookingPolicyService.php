<?php

namespace App\Domain\Bookings;

use App\Enums\BookingNoticeUnit;
use App\Models\Booking;
use Carbon\CarbonImmutable;

class BookingPolicyService
{
    public function canCancel(Booking $booking, ?CarbonImmutable $nowUtc = null): bool
    {
        if (! $booking->cancellation_allowed || in_array($booking->status->value, ['cancelled', 'declined'], true)) {
            return false;
        }

        return $this->beforeCutoff(
            $booking,
            (int) $booking->cancellation_notice_value,
            $this->unit($booking->cancellation_notice_unit, BookingNoticeUnit::Hour),
            $nowUtc,
        );
    }

    public function canReschedule(Booking $booking, ?CarbonImmutable $nowUtc = null): bool
    {
        if (! $booking->rescheduling_allowed || in_array($booking->status->value, ['cancelled', 'declined'], true)) {
            return false;
        }

        if ($booking->tickets()->where('status', 'checked_in')->exists()) {
            return false;
        }

        $maximum = (int) $booking->rescheduling_max_count;
        if ($maximum > 0 && (int) $booking->reschedule_count >= $maximum) {
            return false;
        }

        return $this->beforeCutoff(
            $booking,
            (int) $booking->rescheduling_notice_value,
            $this->unit($booking->rescheduling_notice_unit, BookingNoticeUnit::Hour),
            $nowUtc,
        );
    }

    public function cancellationStatus(Booking $booking, ?CarbonImmutable $nowUtc = null): string
    {
        if (! $booking->cancellation_allowed) {
            return 'Cancellation is not permitted for this appointment.';
        }
        if (in_array($booking->status->value, ['cancelled', 'declined'], true)) {
            return 'This booking is no longer active.';
        }
        if (! $this->canCancel($booking, $nowUtc)) {
            return 'The cancellation deadline has passed.';
        }

        return 'Cancellation is available.';
    }

    public function reschedulingStatus(Booking $booking, ?CarbonImmutable $nowUtc = null): string
    {
        if (! $booking->rescheduling_allowed) {
            return 'Rescheduling is not permitted for this appointment.';
        }
        if (in_array($booking->status->value, ['cancelled', 'declined'], true)) {
            return 'This booking is no longer active.';
        }
        if ($booking->tickets()->where('status', 'checked_in')->exists()) {
            return 'A booking with checked-in tickets cannot be rescheduled.';
        }
        $maximum = (int) $booking->rescheduling_max_count;
        if ($maximum > 0 && (int) $booking->reschedule_count >= $maximum) {
            return 'The maximum number of reschedules has been reached.';
        }
        if (! $this->canReschedule($booking, $nowUtc)) {
            return 'The rescheduling deadline has passed.';
        }

        return 'Rescheduling is available.';
    }

    public function policyLabel(int $value, mixed $unit): string
    {
        $resolved = $this->unit($unit, BookingNoticeUnit::Hour);
        if ($value === 0) {
            return 'Until the appointment starts';
        }

        return $value.' '.$resolved->plural($value).' before start';
    }

    private function beforeCutoff(Booking $booking, int $value, BookingNoticeUnit $unit, ?CarbonImmutable $nowUtc): bool
    {
        $booking->loadMissing(['appointment', 'organization']);
        $nowUtc ??= CarbonImmutable::now('UTC');
        $startLocal = CarbonImmutable::instance($booking->appointment->starts_at_utc)->setTimezone($booking->organization->timezone);
        $cutoffLocal = match ($unit) {
            BookingNoticeUnit::Minute => $startLocal->subMinutes($value),
            BookingNoticeUnit::Hour => $startLocal->subHours($value),
            BookingNoticeUnit::Day => $startLocal->subDays($value),
            BookingNoticeUnit::Week => $startLocal->subWeeks($value),
            BookingNoticeUnit::Month => $startLocal->subMonthsNoOverflow($value),
        };

        return $nowUtc->lt($cutoffLocal->utc());
    }

    private function unit(mixed $value, BookingNoticeUnit $default): BookingNoticeUnit
    {
        return $value instanceof BookingNoticeUnit ? $value : (BookingNoticeUnit::tryFrom((string) $value) ?? $default);
    }
}
