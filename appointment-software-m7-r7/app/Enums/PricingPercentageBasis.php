<?php

namespace App\Enums;

enum PricingPercentageBasis: string
{
    case BasePrice = 'base_price';
    case CurrentSubtotal = 'current_subtotal';
}
