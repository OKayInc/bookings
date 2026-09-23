<?php

namespace App\Enums;

enum PlanAddon: string
{
    case Members = 'members';
    case AppointmentTypes = 'appointment_types';
    case BookingBlocks = 'booking_blocks';
    case Resources = 'resources';
    case CalendarConnections = 'calendar_connections';
    case StorageGb = 'storage_gb';
    case DistanceLookupBlocks = 'distance_lookup_blocks';

    public function label(): string
    {
        return match ($this) {
            self::Members => 'Additional members',
            self::AppointmentTypes => 'Additional active appointment types',
            self::BookingBlocks => 'Additional booking blocks (25 each)',
            self::Resources => 'Additional resources',
            self::CalendarConnections => 'Additional calendar connections',
            self::StorageGb => 'Additional storage (1 GB each)',
            self::DistanceLookupBlocks => 'Additional distance-lookup blocks (250 each)',
        };
    }

    public function limitKey(): string
    {
        return match ($this) {
            self::Members => 'members',
            self::AppointmentTypes => 'active_appointment_types',
            self::BookingBlocks => 'monthly_bookings',
            self::Resources => 'resources',
            self::CalendarConnections => 'calendar_connections',
            self::StorageGb => 'storage_mb',
            self::DistanceLookupBlocks => 'monthly_distance_lookups',
        };
    }

    public function unitsPerQuantity(): int
    {
        return match ($this) {
            self::BookingBlocks => 25,
            self::StorageGb => 1024,
            self::DistanceLookupBlocks => 250,
            default => 1,
        };
    }
}
