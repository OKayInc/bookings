<?php

return [
    'enabled' => (bool) env('PERFORMANCE_LOG_ENABLED', false),
    // Uniform request sampling: 0 disables collection, 1 records every request.
    'sample_rate' => (float) env('PERFORMANCE_LOG_SAMPLE_RATE', 0.1),
];
