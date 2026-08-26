<?php

namespace App\Enums;

enum AvailabilityScope: string
{
    case Organization = 'organization';
    case Resource = 'resource';
    case AppointmentType = 'appointment_type';

    public function label(): string
    {
        return match ($this) {
            self::Organization => 'Organization',
            self::Resource => 'Resource',
            self::AppointmentType => 'Appointment type',
        };
    }
}
