<?php

return [
    'disk' => env('GALLERY_DISK', 'public'),
    'directory' => 'galleries',
    'allowed_mime_types' => ['image/jpeg', 'image/png', 'image/webp'],
    'allowed_extensions' => ['jpg', 'jpeg', 'png', 'webp'],
    'max_upload_kilobytes' => (int) env('GALLERY_MAX_UPLOAD_KB', 20480),
    'max_batch_size' => (int) env('GALLERY_MAX_BATCH_SIZE', 20),
    'max_width' => (int) env('GALLERY_MAX_WIDTH', 2400),
    'max_height' => (int) env('GALLERY_MAX_HEIGHT', 2400),
    'max_megapixels' => (int) env('GALLERY_MAX_MEGAPIXELS', 50),
    'webp_quality' => (int) env('GALLERY_WEBP_QUALITY', 82),

    'limits' => [
        'organization' => [
            'free' => (int) env('GALLERY_FREE_MAX_ORGANIZATION_PHOTOS', 6),
            'paid' => (int) env('GALLERY_PAID_MAX_ORGANIZATION_PHOTOS', 60),
        ],
        'appointment_type' => [
            'free' => (int) env('GALLERY_FREE_MAX_APPOINTMENT_PHOTOS', 6),
            'paid' => (int) env('GALLERY_PAID_MAX_APPOINTMENT_PHOTOS', 30),
        ],
    ],
];
