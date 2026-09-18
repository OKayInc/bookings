<?php

namespace App\Enums;

enum ReminderThresholdBasis: string
{
    case LeadTime = 'lead_time';
    case Duration = 'duration';

    public function label(): string
    {
        return match ($this) {
            self::LeadTime => 'Booked at least this many days ahead',
            self::Duration => 'Appointment lasts at least this many days',
        };
    }
}
