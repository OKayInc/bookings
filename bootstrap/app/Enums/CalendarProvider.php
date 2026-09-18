<?php

namespace App\Enums;

enum CalendarProvider: string
{
    case Google = 'google';
    case Microsoft = 'microsoft';

    public function label(): string
    {
        return match ($this) {
            self::Google => 'Google Calendar',
            self::Microsoft => 'Microsoft Outlook / 365',
        };
    }
}
