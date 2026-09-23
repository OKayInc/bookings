<?php

$complimentaryOrganizations = array_values(array_filter(array_map(
    static fn (string $value): string => strtolower(trim($value)),
    explode(',', (string) env('PLAN_COMPLIMENTARY_UNLIMITED_ORGANIZATION_IDS', '')),
)));

return [
    'platform_owner_user_id' => strtolower(trim((string) env('PLATFORM_OWNER_USER_ID', ''))),
    'complimentary_organization_ids' => $complimentaryOrganizations,
    'trial_days' => max(0, (int) env('PLAN_BUSINESS_TRIAL_DAYS', 14)),
    'billing_grace_days' => max(0, (int) env('PLAN_BILLING_GRACE_DAYS', 3)),
    'currency' => strtolower((string) env('PLAN_BUSINESS_CURRENCY', 'usd')),
    'business_monthly_price_minor' => max(0, (int) env('PLAN_BUSINESS_MONTHLY_PRICE_MINOR', 900)),
    'business_annual_price_minor' => max(0, (int) env('PLAN_BUSINESS_ANNUAL_PRICE_MINOR', 9000)),

    'limits' => [
        'free' => [
            'owned_organizations' => max(0, (int) env('PLAN_FREE_MAX_OWNED_ORGANIZATIONS', 1)),
            'members' => max(0, (int) env('PLAN_FREE_MAX_MEMBERS', 2)),
            'active_appointment_types' => max(0, (int) env('PLAN_FREE_MAX_ACTIVE_APPOINTMENT_TYPES', 3)),
            'monthly_bookings' => max(0, (int) env('PLAN_FREE_MAX_MONTHLY_BOOKINGS', 50)),
            'resources' => max(0, (int) env('PLAN_FREE_MAX_RESOURCES', 3)),
            'person_resources' => max(0, (int) env('PLAN_FREE_MAX_PERSON_RESOURCES', 1)),
            'calendar_connections' => max(0, (int) env('PLAN_FREE_MAX_CALENDAR_CONNECTIONS', 2)),
            'storage_mb' => max(0, (int) env('PLAN_FREE_MAX_STORAGE_MB', 250)),
            'monthly_distance_lookups' => max(0, (int) env('PLAN_FREE_MAX_DISTANCE_LOOKUPS', 25)),
            'questions' => max(0, (int) env('PLAN_FREE_MAX_QUESTIONS', 5)),
        ],
        'business' => [
            'members' => max(0, (int) env('PLAN_BUSINESS_MAX_MEMBERS', 10)),
            'active_appointment_types' => max(0, (int) env('PLAN_BUSINESS_MAX_ACTIVE_APPOINTMENT_TYPES', 25)),
            'monthly_bookings' => max(0, (int) env('PLAN_BUSINESS_MAX_MONTHLY_BOOKINGS', 500)),
            'resources' => max(0, (int) env('PLAN_BUSINESS_MAX_RESOURCES', 25)),
            'person_resources' => max(0, (int) env('PLAN_BUSINESS_MAX_PERSON_RESOURCES', 10)),
            'calendar_connections' => max(0, (int) env('PLAN_BUSINESS_MAX_CALENDAR_CONNECTIONS', 10)),
            'storage_mb' => max(0, (int) env('PLAN_BUSINESS_MAX_STORAGE_MB', 5120)),
            'monthly_distance_lookups' => max(0, (int) env('PLAN_BUSINESS_MAX_DISTANCE_LOOKUPS', 250)),
            'questions' => null,
        ],
    ],

    'addons' => [
        'members' => [
            'unit_amount_minor' => 100,
            'monthly_price_id' => env('PLAN_STRIPE_PRICE_ADDON_MEMBER_MONTHLY'),
            'annual_price_id' => env('PLAN_STRIPE_PRICE_ADDON_MEMBER_ANNUAL'),
        ],
        'appointment_types' => [
            'unit_amount_minor' => 100,
            'monthly_price_id' => env('PLAN_STRIPE_PRICE_ADDON_APPOINTMENT_TYPE_MONTHLY'),
            'annual_price_id' => env('PLAN_STRIPE_PRICE_ADDON_APPOINTMENT_TYPE_ANNUAL'),
        ],
        'booking_blocks' => [
            'unit_amount_minor' => 100,
            'monthly_price_id' => env('PLAN_STRIPE_PRICE_ADDON_BOOKING_BLOCK_MONTHLY'),
            'annual_price_id' => env('PLAN_STRIPE_PRICE_ADDON_BOOKING_BLOCK_ANNUAL'),
        ],
        'resources' => [
            'unit_amount_minor' => 100,
            'monthly_price_id' => env('PLAN_STRIPE_PRICE_ADDON_RESOURCE_MONTHLY'),
            'annual_price_id' => env('PLAN_STRIPE_PRICE_ADDON_RESOURCE_ANNUAL'),
        ],
        'calendar_connections' => [
            'unit_amount_minor' => 100,
            'monthly_price_id' => env('PLAN_STRIPE_PRICE_ADDON_CALENDAR_MONTHLY'),
            'annual_price_id' => env('PLAN_STRIPE_PRICE_ADDON_CALENDAR_ANNUAL'),
        ],
        'storage_gb' => [
            'unit_amount_minor' => 500,
            'monthly_price_id' => env('PLAN_STRIPE_PRICE_ADDON_STORAGE_GB_MONTHLY'),
            'annual_price_id' => env('PLAN_STRIPE_PRICE_ADDON_STORAGE_GB_ANNUAL'),
        ],
        'distance_lookup_blocks' => [
            'unit_amount_minor' => 100,
            'monthly_price_id' => env('PLAN_STRIPE_PRICE_ADDON_DISTANCE_BLOCK_MONTHLY'),
            'annual_price_id' => env('PLAN_STRIPE_PRICE_ADDON_DISTANCE_BLOCK_ANNUAL'),
        ],
    ],

    'stripe' => [
        'api_url' => env('PLAN_STRIPE_API_URL', 'https://api.stripe.com'),
        'api_version' => env('PLAN_STRIPE_API_VERSION', '2025-06-30.basil'),
        'secret_key' => env('PLAN_STRIPE_SECRET_KEY'),
        'webhook_secret' => env('PLAN_STRIPE_WEBHOOK_SECRET'),
        'monthly_price_id' => env('PLAN_STRIPE_PRICE_BUSINESS_MONTHLY'),
        'annual_price_id' => env('PLAN_STRIPE_PRICE_BUSINESS_ANNUAL'),
        'request_timeout_seconds' => max(1, (int) env('PLAN_STRIPE_TIMEOUT_SECONDS', 20)),
        'webhook_tolerance_seconds' => max(0, (int) env('PLAN_STRIPE_WEBHOOK_TOLERANCE_SECONDS', 300)),
    ],

    'adsense' => [
        'enabled' => filter_var(env('PLAN_ADSENSE_ENABLED', false), FILTER_VALIDATE_BOOL),
        'client' => trim((string) env('PLAN_ADSENSE_CLIENT', '')),
        'slot' => trim((string) env('PLAN_ADSENSE_SLOT', '')),
    ],
];
