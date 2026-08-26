<?php

namespace App\Domain\Availability;

use App\Models\BookingHold;

final readonly class BookingHoldLease
{
    public function __construct(
        public BookingHold $hold,
        public string $token,
    ) {
    }
}
