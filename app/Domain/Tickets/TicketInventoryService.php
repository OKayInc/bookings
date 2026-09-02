<?php

namespace App\Domain\Tickets;

use App\Enums\BookingHoldStatus;
use App\Enums\TicketStatus;
use App\Models\Appointment;
use App\Models\AppointmentType;
use App\Models\Booking;
use App\Models\BookingHold;
use App\Models\Ticket;
use RuntimeException;

class TicketInventoryService
{
    public function __construct(private readonly TicketSeatingService $seating)
    {
    }

    /** @return list<array{seat_key:?string,section_label:?string,row_label:?string,seat_label:?string,seat_fee_minor:int}> */
    public function reserveForType(AppointmentType $type, int $quantity): array
    {
        return $this->take($this->seating->inventoryForType($type), $quantity);
    }

    /** @return list<array{seat_key:?string,section_label:?string,row_label:?string,seat_label:?string,seat_fee_minor:int}> */
    public function reserveForAppointment(
        Appointment $appointment,
        int $quantity,
        ?BookingHold $ignoredHold = null,
        ?Booking $ignoredBooking = null,
    ): array {
        return $this->take($this->availableForAppointment($appointment, $ignoredHold, $ignoredBooking), $quantity);
    }

    /** @return list<array{seat_key:?string,section_label:?string,row_label:?string,seat_label:?string,seat_fee_minor:int}> */
    private function availableForAppointment(
        Appointment $appointment,
        ?BookingHold $ignoredHold = null,
        ?Booking $ignoredBooking = null,
    ): array {
        $usedKeys = Ticket::query()
            ->where('appointment_id', $appointment->getKey())
            ->where('status', '!=', TicketStatus::Voided->value)
            ->when($ignoredBooking !== null, fn ($query) => $query->where('booking_id', '!=', $ignoredBooking->getKey()))
            ->whereNotNull('seat_key')
            ->pluck('seat_key')
            ->all();

        $holds = BookingHold::query()
            ->where('appointment_id', $appointment->getKey())
            ->where('status', BookingHoldStatus::Active->value)
            ->where('expires_at_utc', '>', now('UTC'))
            ->when($ignoredHold !== null, fn ($query) => $query->where($ignoredHold->getKeyName(), '!=', $ignoredHold->getKey()))
            ->get(['id', 'ticket_seats']);
        foreach ($holds as $hold) {
            foreach ($hold->ticket_seats ?? [] as $seat) {
                if (filled($seat['seat_key'] ?? null)) {
                    $usedKeys[] = $seat['seat_key'];
                }
            }
        }

        $used = array_fill_keys($usedKeys, true);
        $available = array_values(array_filter(
            $this->seating->inventory($appointment),
            fn (array $seat): bool => $seat['seat_key'] === null || ! isset($used[$seat['seat_key']]),
        ));

        return $available;
    }

    /**
     * @param array<int, mixed> $reserved
     * @return list<array{seat_key:?string,section_label:?string,row_label:?string,seat_label:?string,seat_fee_minor:int}>
     */
    public function validateReservation(
        Appointment $appointment,
        array $reserved,
        int $quantity,
        ?BookingHold $hold = null,
        ?Booking $booking = null,
    ): array {
        if (count($reserved) !== $quantity) {
            throw new RuntimeException('The ticket hold no longer contains the expected number of seats.');
        }

        $available = $this->availableForAppointment($appointment, $hold, $booking);
        $byKey = [];
        $unnumbered = [];
        foreach ($available as $seat) {
            if ($seat['seat_key'] === null) {
                $unnumbered[] = $seat;
            } else {
                $byKey[$seat['seat_key']] = $seat;
            }
        }

        $validated = [];
        $seen = [];
        foreach ($reserved as $seat) {
            if (! is_array($seat)) {
                throw new RuntimeException('The ticket hold contains invalid seating data.');
            }
            $heldFee = filter_var($seat['seat_fee_minor'] ?? 0, FILTER_VALIDATE_INT);
            if ($heldFee === false || $heldFee < 0) {
                throw new RuntimeException('The ticket hold contains an invalid seating fee.');
            }
            $key = $seat['seat_key'] ?? null;
            if ($key === null) {
                $canonical = array_shift($unnumbered);
            } else {
                if (isset($seen[$key])) {
                    throw new RuntimeException('The ticket hold contains the same seat more than once.');
                }
                $canonical = $byKey[$key] ?? null;
                unset($byKey[$key]);
                $seen[$key] = true;
            }
            if ($canonical === null) {
                throw new RuntimeException('A held ticket seat is no longer available.');
            }
            // Seat identity comes from current inventory, while the fee remains the
            // server-created amount quoted and reserved on this hold.
            $canonical['seat_fee_minor'] = $heldFee;
            $validated[] = $canonical;
        }

        return $validated;
    }

    /**
     * @param list<array{seat_key:?string,section_label:?string,row_label:?string,seat_label:?string,seat_fee_minor:int}> $inventory
     * @return list<array{seat_key:?string,section_label:?string,row_label:?string,seat_label:?string,seat_fee_minor:int}>
     */
    private function take(array $inventory, int $quantity): array
    {
        if ($quantity < 1 || count($inventory) < $quantity) {
            throw new RuntimeException('This event no longer has enough ticket inventory for the requested booking.');
        }

        return array_values(array_slice($inventory, 0, $quantity));
    }
}
