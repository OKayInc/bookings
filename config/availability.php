<?php

return [
    'batch_resource_checks' => (bool) env('AVAILABILITY_BATCH_RESOURCE_CHECKS', true),
    'default_start_interval_minutes' => 15,
    'max_start_interval_minutes' => 1440,
    'hold_ttl_minutes' => 10,
    'max_preview_days' => 31,
    'max_exception_days' => 366,
];
