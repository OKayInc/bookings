<?php

namespace App\Domain\Tickets;

use App\Enums\TicketStatus;
use App\Models\Booking;
use App\Models\BookingHold;
use App\Models\Ticket;
use Illuminate\Support\Str;
use RuntimeException;

class TicketAllocationService
{
    public function __construct(
        private readonly TicketInventoryService $inventory,
        private readonly TicketSeatPricingService $seatPricing,
    ) {
    }

    /** @param array<int, mixed> $reservedSeats */
    public function createForBooking(Booking $booking, array $reservedSeats = [], ?BookingHold $hold = null): void
    {
        $booking->loadMissing(['appointment', 'attendees']);
        if (! $booking->appointment->ticketing_enabled) {
            return;
        }

        $quantity = $booking->attendees->count();
        $seats = $reservedSeats === []
            ? $this->inventory->reserveForAppointment($booking->appointment, $quantity, $hold)
            : $this->inventory->validateReservation($booking->appointment, $reservedSeats, $quantity, $hold);

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

    /** @param array<int, mixed> $reservedSeats */
    public function reassignForBooking(Booking $booking, array $reservedSeats = [], ?BookingHold $hold = null): void
    {
        $booking->loadMissing(['appointment', 'tickets']);
        if (! $booking->appointment->ticketing_enabled) {
            return;
        }
        if ($booking->tickets->contains(fn (Ticket $ticket): bool => $ticket->status === TicketStatus::CheckedIn)) {
            throw new RuntimeException('A booking with checked-in tickets cannot be rescheduled.');
        }

        $quantity = $booking->tickets->count();
        $seats = $reservedSeats === []
            ? $this->inventory->reserveForAppointment($booking->appointment, $quantity, $hold, $booking)
            : $this->inventory->validateReservation($booking->appointment, $reservedSeats, $quantity, $hold, $booking);
        $currentSeatFees = $booking->tickets->sum(fn (Ticket $ticket): int => (int) $ticket->seat_fee_minor);
        if ($this->seatPricing->total($seats) !== $currentSeatFees) {
            throw new RuntimeException('The proposed event would change the booking seating fee. Choose an event with the same seating fee or cancel and create a new booking.');
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

    private function uniqueCode(): string
    {
        do {
            $code = 'AT-'.Str::upper(Str::random(14));
        } while (Ticket::query()->where('code', $code)->exists());

        return $code;
    }
}
