<?php

namespace App\Enums;

enum BookingHoldStatus: string
{
    case Active = 'active';
    case Released = 'released';
    case Consumed = 'consumed';
    case Expired = 'expired';
}
