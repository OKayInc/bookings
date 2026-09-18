<?php

return [
    'request_timeout_seconds' => (int) env('PAYMENT_REQUEST_TIMEOUT_SECONDS', 20),
    'checkout_ttl_minutes' => (int) env('PAYMENT_CHECKOUT_TTL_MINUTES', 60),
    'booking_payment_window_minutes' => (int) env('PAYMENT_BOOKING_WINDOW_MINUTES', 60),
    'stripe' => [
        'api_url' => env('STRIPE_API_URL', 'https://api.stripe.com'),
        'webhook_tolerance_seconds' => (int) env('STRIPE_WEBHOOK_TOLERANCE_SECONDS', 300),
    ],
    'paypal' => [
        'sandbox_api_url' => env('PAYPAL_SANDBOX_API_URL', 'https://api-m.sandbox.paypal.com'),
        'live_api_url' => env('PAYPAL_LIVE_API_URL', 'https://api-m.paypal.com'),
    ],
];
