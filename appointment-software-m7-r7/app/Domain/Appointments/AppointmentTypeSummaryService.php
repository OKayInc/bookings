<?php

namespace App\Domain\Appointments;

use App\Domain\Bookings\BookingNoticeService;
use App\Domain\Money\MoneyService;
use App\Enums\AttendanceMode;
use App\Enums\DurationMode;
use App\Enums\PricingMode;
use App\Models\AppointmentType;

class AppointmentTypeSummaryService
{
    public function __construct(
        private readonly MoneyService $money,
        private readonly AppointmentTypePricingService $pricing,
        private readonly BookingNoticeService $notice,
    ) {
    }

    public function duration(AppointmentType $type): string
    {
        $unit = $type->duration_unit;

        if ($type->duration_mode === DurationMode::Fixed) {
            return $type->duration_value.' '.$unit->plural((int) $type->duration_value);
        }

        return sprintf(
            '%d–%d %s, every %d %s',
            $type->minimum_duration_value,
            $type->maximum_duration_value,
            $unit->plural((int) $type->maximum_duration_value),
            $type->duration_increment_value,
            $unit->plural((int) $type->duration_increment_value),
        );
    }

    public function pricing(AppointmentType $type): string
    {
        $currency = $type->organization->currency;

        return match ($type->pricing_mode) {
            PricingMode::Free => 'Free',
            PricingMode::Fixed => $this->money->format((int) $type->fixed_price_minor, $currency),
            PricingMode::Rate => sprintf(
                '%s / %s',
                $this->money->format((int) $type->rate_amount_minor, $currency),
                $type->rate_unit->value,
            ),
        };
    }

    public function examplePrice(AppointmentType $type): int
    {
        return $this->pricing->priceForDuration($type);
    }

    public function bookingNotice(AppointmentType $type): string
    {
        return $this->notice->label($type);
    }

    public function maximumBookingNotice(AppointmentType $type): string
    {
        return $this->notice->maximumLabel($type);
    }

    public function attendance(AppointmentType $type): string
    {
        return $type->attendance_mode === AttendanceMode::Single
            ? '1 attendee'
            : 'Up to '.number_format((int) $type->capacity).' attendees per session';
    }
}
