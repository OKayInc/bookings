<?php

namespace App\Enums;

enum CouponSource: string
{
    case Manual = 'manual';
    case Purchased = 'purchased';
}
