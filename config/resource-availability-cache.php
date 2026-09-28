<?php

return [
    'store' => env('RESOURCE_AVAILABILITY_CACHE_STORE', 'redis'),
    // Browsing only. Reservations always bypass this cache. Zero disables it.
    'seconds' => (int) env('RESOURCE_AVAILABILITY_CACHE_SECONDS', 30),
];
