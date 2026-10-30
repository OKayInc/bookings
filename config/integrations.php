<?php

return [
    'queue' => env('INTEGRATION_QUEUE', 'integrations'),
    'lock_store' => env('INTEGRATION_LOCK_STORE', 'redis'),
];
