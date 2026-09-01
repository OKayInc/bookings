<?php

namespace App\Domain\Tickets;

use App\Models\AppointmentType;
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
        if (! $type->ticketing_enabled) {
            return [
                'ticketing_enabled' => false,
                'show_starts_at_utc' => null,
                'show_ends_at_utc' => null,
                'ticket_seating_scheme' => null,
                'ticket_seat_optional' => false,
                'ticket_seat_blocks' => null,
            ];
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
            'ticketing_enabled' => true,
            'show_starts_at_utc' => $showStarts,
            'show_ends_at_utc' => $showEnds,
            'ticket_seating_scheme' => $type->ticket_seating_scheme?->value ?? 'none',
            'ticket_seat_optional' => (bool) $type->ticket_seat_optional,
            'ticket_seat_blocks' => $type->ticket_seat_blocks,
        ];
    }
}
