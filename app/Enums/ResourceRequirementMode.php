<?php

namespace App\Enums;

enum ResourceRequirementMode: string
{
    case Inherit = 'inherit';
    case Required = 'required';
    case Optional = 'optional';

    public function label(): string
    {
        return match ($this) {
            self::Inherit => 'Use organization default',
            self::Required => 'Required',
            self::Optional => 'Optional',
        };
    }
}
