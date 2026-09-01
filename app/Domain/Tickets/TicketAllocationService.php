<?php

namespace App\Domain\Tickets;

use App\Enums\TicketStatus;
use App\Models\Booking;
use App\Models\Ticket;
use Illuminate\Support\Str;
use RuntimeException;

class TicketAllocationService
{
    public function __construct(private readonly TicketSeatingService $seating)
    {
    }

    public function createForBooking(Booking $booking): void
    {
        $booking->loadMissing(['appointment', 'attendees']);
        if (! $booking->appointment->ticketing_enabled) {
            return;
        }

        $seats = $this->availableSeats($booking);
        if (count($seats) < $booking->attendees->count()) {
            throw new RuntimeException('This event no longer has enough ticket inventory for the booking.');
        }

        foreach ($booking->attendees as $position => $attendee) {
            $seat = $seats[$position];
            Ticket::create([
                'organization_id' => $booking->organization_id,
                'appointment_id' => $booking->appointment_id,
                'booking_id' => $booking->getKey(),
                'booking_attendee_id' => $attendee->getKey(),
                'code' => $this->uniqueCode(),
                'status' => TicketStatus::Reserved->value,
                ...$seat,
            ]);
        }
    }

    public function reassignForBooking(Booking $booking): void
    {
        $booking->loadMissing(['appointment', 'tickets']);
        if (! $booking->appointment->ticketing_enabled) {
            return;
        }
        if ($booking->tickets->contains(fn (Ticket $ticket): bool => $ticket->status === TicketStatus::CheckedIn)) {
            throw new RuntimeException('A booking with checked-in tickets cannot be rescheduled.');
        }

        $seats = $this->availableSeats($booking);
        if (count($seats) < $booking->tickets->count()) {
            throw new RuntimeException('The proposed event no longer has enough ticket inventory for this booking.');
        }

        foreach ($booking->tickets->values() as $position => $ticket) {
            $ticket->update([
                'appointment_id' => $booking->appointment_id,
                'status' => TicketStatus::Reserved->value,
                'checked_in_at_utc' => null,
                'checked_in_by_person_id' => null,
                ...$seats[$position],
            ]);
        }
    }

    /** @return list<array{seat_key:?string,section_label:?string,row_label:?string,seat_label:?string}> */
    private function availableSeats(Booking $booking): array
    {
        $inventory = $this->seating->inventory($booking->appointment);
        $used = Ticket::query()
            ->where('appointment_id', $booking->appointment_id)
            ->where('booking_id', '!=', $booking->getKey())
            ->whereNotNull('seat_key')
            ->pluck('seat_key')
            ->flip();

        return array_values(array_filter(
            $inventory,
            fn (array $seat): bool => $seat['seat_key'] === null || ! $used->has($seat['seat_key']),
        ));
    }

    private function uniqueCode(): string
    {
        do {
            $code = 'AT-'.Str::upper(Str::random(14));
        } while (Ticket::query()->where('code', $code)->exists());

        return $code;
    }
}
