<?php

namespace App\Domain\Appointments;

use App\Domain\Bookings\BookingNoticeService;
use App\Domain\Money\MoneyService;
use App\Domain\Resources\EquipmentPricingService;
use App\Enums\AttendanceMode;
use App\Enums\AttendeePricingMode;
use App\Enums\DurationMode;
use App\Enums\PricingMode;
use App\Enums\SeasonRecurrence;
use App\Enums\TicketSeatingScheme;
use App\Models\AppointmentType;

class AppointmentTypeSummaryService
{
    public function __construct(
        private readonly MoneyService $money,
        private readonly AppointmentTypePricingService $pricing,
        private readonly BookingNoticeService $notice,
        private readonly EquipmentPricingService $equipmentPricing,
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

        $summary = match ($type->pricing_mode) {
            PricingMode::Free => 'Free',
            PricingMode::Fixed => $this->money->format((int) $type->fixed_price_minor, $currency),
            PricingMode::PerAttendee => match ($type->attendee_pricing_mode ?? AttendeePricingMode::Flat) {
                AttendeePricingMode::Flat => $this->money->format((int) $type->attendee_price_minor, $currency).' / attendee',
                AttendeePricingMode::Absolute => 'Per attendee · absolute ranges',
                AttendeePricingMode::Accumulative => 'Per attendee · accumulative ranges',
            },
            PricingMode::Rate => sprintf(
                '%s / %s',
                $this->money->format((int) $type->rate_amount_minor, $currency),
                $type->rate_unit->value,
            ),
        };

        $hasSeatingFees = $type->ticketing_enabled && collect($type->ticket_seat_blocks ?? [])
            ->contains(fn (array $block): bool => (int) ($block['seat_fee_minor'] ?? 0) > 0);
        $equipmentTotal = $this->equipmentPricing->total($type);
        if ($equipmentTotal > 0) {
            $equipmentLabel = $this->money->format($equipmentTotal, $currency).' equipment';
            $summary = $type->pricing_mode === PricingMode::Free
                ? $equipmentLabel
                : $summary.' + '.$equipmentLabel;
        }

        return $hasSeatingFees ? $summary.' + allocated seating fees' : $summary;
    }

    public function examplePrice(AppointmentType $type): int
    {
        $base = $this->pricing->priceForDuration($type);
        $equipment = $this->equipmentPricing->total($type);
        if ($equipment > PHP_INT_MAX - $base) {
            throw new \InvalidArgumentException('The appointment price is too large.');
        }

        return $base + $equipment;
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
        if ($type->ticketing_enabled) {
            return 'Up to '.number_format((int) $type->capacity).' tickets per event';
        }

        return $type->attendance_mode === AttendanceMode::Single
            ? '1 attendee'
            : 'Up to '.number_format((int) $type->capacity).' attendees per session';
    }

    public function seating(AppointmentType $type): string
    {
        if (! $type->ticketing_enabled) {
            return 'Not a ticketed event';
        }

        $scheme = $type->ticket_seating_scheme ?? TicketSeatingScheme::None;
        $description = $scheme->label();
        if ($type->ticket_seat_optional && $scheme->supportsOptionalSeat()) {
            $description .= ' · seat number may be omitted';
        }

        return $description;
    }

    public function location(AppointmentType $type): string
    {
        if (! $type->is_online || $type->meeting_provider === null) {
            return 'In person or arranged by the organization';
        }

        return 'Online · '.$type->meeting_provider->label();
    }

    public function season(AppointmentType $type): string
    {
        if (! $type->seasonal_availability_enabled
            || $type->season_start_date === null
            || $type->season_end_date === null
            || $type->season_recurrence === null) {
            return 'Year-round';
        }

        if ($type->season_recurrence === SeasonRecurrence::Once) {
            return $type->season_start_date->format('M j, Y').' – '.$type->season_end_date->format('M j, Y');
        }

        return $type->season_start_date->format('M j').' – '.$type->season_end_date->format('M j').' every year';
    }
}
