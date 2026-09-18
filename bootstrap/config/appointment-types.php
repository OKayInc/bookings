<?php

return [
    'logo_disk' => env('APPOINTMENT_LOGO_DISK', 'public'),
    'logo_directory' => 'appointment-types/logos',
    'logo_extensions' => ['jpg', 'jpeg', 'png', 'webp'],
    'max_logo_kilobytes' => 5120,

    // Validation safety limits. These do not prescribe business rules; they
    // prevent accidental values large enough to overflow date/price arithmetic.
    'max_duration' => [
        'minute' => 5256000, // 10 years
        'hour' => 87600,
        'day' => 3650,
        'week' => 520,
    ],
    'max_capacity' => 100000,
    'max_buffer_minutes' => 10080,
    'max_booking_notice' => [
        'minute' => 5256000, // 10 years
        'hour' => 87600,
        'day' => 3650,
        'week' => 520,
        'month' => 120,
    ],
];
