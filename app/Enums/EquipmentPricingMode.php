<?php

namespace App\Enums;

enum EquipmentPricingMode: string
{
    case Free = 'free';
    case PerUnit = 'per_unit';
    case Bundles = 'bundles';
    case Fixed = 'fixed';

    public function label(): string
    {
        return match ($this) {
            self::Free => 'Free',
            self::PerUnit => 'Per piece',
            self::Bundles => 'Bundle schedule',
            self::Fixed => 'Fixed rental fee',
        };
    }
}
