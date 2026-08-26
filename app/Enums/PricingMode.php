<?php

namespace App\Enums;

enum PricingMode: string
{
    case Free = 'free';
    case Fixed = 'fixed';
    case Rate = 'rate';
}
