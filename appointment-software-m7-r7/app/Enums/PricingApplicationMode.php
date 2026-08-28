<?php

namespace App\Enums;

enum PricingApplicationMode: string
{
    case Once = 'once';
    case PerUnit = 'per_unit';
}
