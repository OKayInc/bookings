<?php

namespace App\Domain\Bookings;

use App\Models\Booking;

final readonly class BookingCreationResult
{
    public function __construct(
        public Booking $booking,
        public ?string $emailVerificationToken,
        public string $manageToken,
    ) {
    }
}
