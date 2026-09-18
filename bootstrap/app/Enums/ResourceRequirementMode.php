<?php

namespace App\Enums;

enum ResourceRequirementMode: string
{
    case Inherit = 'inherit';
    case Required = 'required';
    case Replacement = 'replacement';
    case Optional = 'optional';

    public function label(): string
    {
        return match ($this) {
            self::Inherit => 'Use organization default',
            self::Required => 'Required',
            self::Replacement => 'One of a replacement group',
            self::Optional => 'Optional',
        };
    }
}
