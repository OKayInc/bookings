<?php

namespace App\Domain\Tickets;

use App\Domain\Availability\BookableSlot;
use App\Enums\AttendanceMode;
use App\Enums\DurationMode;
use App\Enums\PricingMode;
use App\Models\AppointmentType;
use App\Models\EventOccurrence;
use Carbon\CarbonImmutable;
use RuntimeException;

class TicketEventService
{
    /** @return array<string, mixed> */
    public function appointmentAttributes(
        AppointmentType $type,
        CarbonImmutable $startsAtUtc,
        CarbonImmutable $endsAtUtc,
    ): array {
        // Booking writes always read the current occurrence, even if a caller loaded a relation.
        $occurrence = $type->ticketing_enabled
            ? $type->eventOccurrences()->where('is_active', true)
                ->where('starts_at_utc', $startsAtUtc->utc()->format('Y-m-d H:i:s.u'))->first()
            : null;

        return $this->attributes($type, $startsAtUtc, $endsAtUtc, $occurrence);
    }

    /**
     * A response-local snapshot for browsing. Never used to authorize a booking write.
     *
     * @param list<BookableSlot> $slots
     * @return list<array<string, mixed>>
     */
    public function attributesForSlots(AppointmentType $type, array $slots): array
    {
        if ($slots === []) {
            return [];
        }

        $occurrences = $type->ticketing_enabled
            ? $type->eventOccurrences()->where('is_active', true)
                ->whereIn('starts_at_utc', array_map(
                    static fn (BookableSlot $slot): string => $slot->startsAtUtc->utc()->format('Y-m-d H:i:s.u'),
                    $slots,
                ))->get()->keyBy(fn (EventOccurrence $occurrence): string => $occurrence->starts_at_utc->utc()->format('Y-m-d H:i:s.u'))
            : collect();

        return array_map(fn (BookableSlot $slot): array => $this->attributes(
            $type,
            $slot->startsAtUtc,
            $slot->endsAtUtc,
            $occurrences->get($slot->startsAtUtc->utc()->format('Y-m-d H:i:s.u')),
        ), $slots);
    }

    /** @return array<string, mixed> */
    private function attributes(
        AppointmentType $type,
        CarbonImmutable $startsAtUtc,
        CarbonImmutable $endsAtUtc,
        ?EventOccurrence $occurrence,
    ): array {
        if (! $type->ticketing_enabled) {
            return [
                'event_occurrence_id' => null,
                'ticketing_enabled' => false,
                'private_event_enabled' => false,
                'event_location' => null,
                'location_disclosure_mode' => 'public',
                'location_disclosure_hours' => null,
                'show_starts_at_utc' => null,
                'show_ends_at_utc' => null,
                'ticket_seating_scheme' => null,
                'ticket_seat_optional' => false,
                'ticket_seat_blocks' => null,
            ];
        }

        if ($type->attendance_mode !== AttendanceMode::Group) {
            throw new RuntimeException('Ticketed events must use group attendance.');
        }
        if ($type->duration_mode !== DurationMode::Fixed) {
            throw new RuntimeException('Ticketed events must use a fixed duration.');
        }
        if (! in_array($type->pricing_mode, [PricingMode::Free, PricingMode::PerAttendee], true)) {
            throw new RuntimeException('Ticketed events must use free or per-attendee pricing.');
        }

        if (! $occurrence) {
            throw new RuntimeException('Please select the configured event date and time.');
        }

        $showStarts = $startsAtUtc->addMinutes((int) $type->show_start_offset_minutes);
        $showEnds = $type->show_end_offset_minutes === null
            ? null
            : $startsAtUtc->addMinutes((int) $type->show_end_offset_minutes);

        if ($showStarts->lt($startsAtUtc) || $showStarts->gt($endsAtUtc)) {
            throw new RuntimeException('The configured show start must fall between doors open and the booking end.');
        }
        if ($showEnds !== null && ($showEnds->lt($showStarts) || $showEnds->gt($endsAtUtc))) {
            throw new RuntimeException('The configured show end must fall between show start and the booking end.');
        }

        return [
            'event_occurrence_id' => $occurrence->getKey(),
            'ticketing_enabled' => true,
            'private_event_enabled' => (bool) $type->private_event_enabled,
            'event_location' => $occurrence->venue,
            'location_disclosure_mode' => $type->location_disclosure_mode?->value ?? 'public',
            'location_disclosure_hours' => $type->location_disclosure_hours,
            'show_starts_at_utc' => $showStarts,
            'show_ends_at_utc' => $showEnds,
            'ticket_seating_scheme' => $type->ticket_seating_scheme?->value ?? 'none',
            'ticket_seat_optional' => (bool) $type->ticket_seat_optional,
            'ticket_seat_blocks' => $type->ticket_seat_blocks,
        ];
    }
}
