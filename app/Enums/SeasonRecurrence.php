<?php

namespace App\Enums;

enum SeasonRecurrence: string
{
    case Once = 'once';
    case Yearly = 'yearly';

    public function label(): string
    {
        return match ($this) {
            self::Once => 'One time only',
            self::Yearly => 'Repeat every year',
        };
    }
}
