<?php

namespace App\Enums;

enum PlanLevel: string
{
    case Free = 'free';
    case Business = 'business';
    case Complimentary = 'complimentary';

    public function label(): string
    {
        return match ($this) {
            self::Free => 'Free',
            self::Business => 'Business',
            self::Complimentary => 'Complimentary Unlimited',
        };
    }

    public function hasBusinessFeatures(): bool
    {
        return $this !== self::Free;
    }

    public function isUnlimited(): bool
    {
        return $this === self::Complimentary;
    }
}
