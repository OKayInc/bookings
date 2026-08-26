<?php

namespace App\Domain\Bookings;

use App\Enums\BookingNoticeUnit;
use App\Models\AppointmentType;
use Carbon\CarbonImmutable;

class BookingNoticeService
{
    public function earliestBookableStartUtc(
        AppointmentType $type,
        ?CarbonImmutable $nowUtc = null,
    ): CarbonImmutable {
        $type->loadMissing('organization');
        $nowUtc ??= CarbonImmutable::now('UTC');

        $value = max(0, (int) $type->booking_notice_value);
        if ($value === 0) {
            return $nowUtc->utc();
        }

        return $this->applyNotice(
            $nowUtc,
            $type->organization->timezone,
            $value,
            $this->unit($type->booking_notice_unit, BookingNoticeUnit::Hour),
        );
    }

    public function latestBookableStartUtc(
        AppointmentType $type,
        ?CarbonImmutable $nowUtc = null,
    ): ?CarbonImmutable {
        $type->loadMissing('organization');
        $nowUtc ??= CarbonImmutable::now('UTC');

        $value = max(0, (int) ($type->maximum_booking_notice_value ?? 365));
        if ($value === 0) {
            return null;
        }

        return $this->applyNotice(
            $nowUtc,
            $type->organization->timezone,
            $value,
            $this->unit($type->maximum_booking_notice_unit, BookingNoticeUnit::Day),
        );
    }

    public function permits(
        AppointmentType $type,
        CarbonImmutable $startsAtUtc,
        ?CarbonImmutable $nowUtc = null,
    ): bool {
        $nowUtc ??= CarbonImmutable::now('UTC');
        $minimumValue = max(0, (int) $type->booking_notice_value);
        $earliest = $this->earliestBookableStartUtc($type, $nowUtc);

        $minimumSatisfied = $minimumValue === 0
            ? $startsAtUtc->gt($earliest)
            : $startsAtUtc->gte($earliest);

        if (! $minimumSatisfied) {
            return false;
        }

        $latest = $this->latestBookableStartUtc($type, $nowUtc);

        return $latest === null || $startsAtUtc->lte($latest);
    }

    public function failureMessage(
        AppointmentType $type,
        CarbonImmutable $startsAtUtc,
        ?CarbonImmutable $nowUtc = null,
    ): ?string {
        $nowUtc ??= CarbonImmutable::now('UTC');
        $minimumValue = max(0, (int) $type->booking_notice_value);
        $earliest = $this->earliestBookableStartUtc($type, $nowUtc);
        $tooSoon = $minimumValue === 0
            ? $startsAtUtc->lte($earliest)
            : $startsAtUtc->lt($earliest);

        if ($tooSoon) {
            return 'This appointment requires more advance notice. Please choose a later time.';
        }

        $latest = $this->latestBookableStartUtc($type, $nowUtc);
        if ($latest !== null && $startsAtUtc->gt($latest)) {
            return 'This appointment cannot be booked that far in advance. Please choose an earlier time.';
        }

        return null;
    }

    public function minimumLabel(AppointmentType $type): string
    {
        $value = max(0, (int) $type->booking_notice_value);
        if ($value === 0) {
            return 'No minimum notice';
        }

        $unit = $this->unit($type->booking_notice_unit, BookingNoticeUnit::Hour);

        return $value.' '.$unit->plural($value).' minimum notice';
    }

    public function maximumLabel(AppointmentType $type): string
    {
        $value = max(0, (int) ($type->maximum_booking_notice_value ?? 365));
        if ($value === 0) {
            return 'No maximum advance-booking limit';
        }

        $unit = $this->unit($type->maximum_booking_notice_unit, BookingNoticeUnit::Day);

        return 'Up to '.$value.' '.$unit->plural($value).' in advance';
    }

    public function label(AppointmentType $type): string
    {
        return $this->minimumLabel($type).'; '.$this->maximumLabel($type);
    }

    private function applyNotice(
        CarbonImmutable $nowUtc,
        string $timezone,
        int $value,
        BookingNoticeUnit $unit,
    ): CarbonImmutable {
        $local = $nowUtc->setTimezone($timezone);
        $result = match ($unit) {
            BookingNoticeUnit::Minute => $local->addMinutes($value),
            BookingNoticeUnit::Hour => $local->addHours($value),
            BookingNoticeUnit::Day => $local->addDays($value),
            BookingNoticeUnit::Week => $local->addWeeks($value),
            BookingNoticeUnit::Month => $local->addMonthsNoOverflow($value),
        };

        return $result->utc();
    }

    private function unit(mixed $value, BookingNoticeUnit $default): BookingNoticeUnit
    {
        if ($value instanceof BookingNoticeUnit) {
            return $value;
        }

        return BookingNoticeUnit::tryFrom((string) $value) ?? $default;
    }
}
