<?php

namespace App\Domain\Tickets;

use App\Enums\BookingStatus;
use App\Enums\TicketStatus;
use App\Models\Booking;

class TicketLifecycleService
{
    public function sync(Booking $booking): void
    {
        if (! $booking->tickets()->exists()) {
            return;
        }

        if (in_array($booking->status, [BookingStatus::Cancelled, BookingStatus::Declined], true)) {
            $booking->tickets()
                ->where('status', '!=', TicketStatus::Voided->value)
                ->update([
                    'status' => TicketStatus::Voided->value,
                    'seat_key' => null,
                    'updated_at' => now(),
                ]);

            return;
        }

        if ($booking->status === BookingStatus::Confirmed) {
            $booking->tickets()
                ->where('status', TicketStatus::Reserved->value)
                ->update(['status' => TicketStatus::Issued->value, 'updated_at' => now()]);

            return;
        }

        $booking->tickets()
            ->where('status', TicketStatus::Issued->value)
            ->update(['status' => TicketStatus::Reserved->value, 'updated_at' => now()]);
    }
}
