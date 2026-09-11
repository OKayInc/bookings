<?php

namespace App\Exceptions;

use App\Models\BookingHold;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ExpiredBookingHoldException extends HttpException
{
    public function __construct(
        public readonly BookingHold $hold,
        public readonly string $returnUrl,
    ) {
        parent::__construct(410, 'This booking hold has expired.');
    }
}
