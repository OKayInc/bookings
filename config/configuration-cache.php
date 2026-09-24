<?php

return [
    // All HTTP nodes must use the same Redis instance for configuration reads.
    'store' => env('CONFIGURATION_CACHE_STORE', 'redis'),
    'ttl' => (int) env('CONFIGURATION_CACHE_TTL', 600),
];
