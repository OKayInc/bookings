<?php

namespace App\Enums;

enum ConditionalResourceFulfillmentMode: string
{
    case OneOf = 'one_of';
    case All = 'all';

    public function label(): string
    {
        return match ($this) {
            self::OneOf => 'One of the selected resources (1 of N)',
            self::All => 'Every selected resource (all)',
        };
    }
}
