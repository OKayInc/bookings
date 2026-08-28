<?php

namespace App\Enums;

enum PricingAdjustmentType: string
{
    case None = 'none';
    case Fixed = 'fixed';
    case Percentage = 'percentage';
}
