<?php

return [
    'http_timeout_seconds' => (int) env('CALENDAR_HTTP_TIMEOUT_SECONDS', 8),
    'busy_cache_seconds' => (int) env('CALENDAR_BUSY_CACHE_SECONDS', 30),
    'sync_days_back' => (int) env('CALENDAR_SYNC_DAYS_BACK', 2),
    'sync_days_ahead' => (int) env('CALENDAR_SYNC_DAYS_AHEAD', 730),

    'google' => [
        'client_id' => env('GOOGLE_CALENDAR_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CALENDAR_CLIENT_SECRET'),
        'redirect_uri' => env('GOOGLE_CALENDAR_REDIRECT_URI', rtrim((string) env('APP_URL', 'http://localhost'), '/').'/calendar-connections/oauth/google/callback'),
        'scopes' => [
            'openid',
            'email',
            'https://www.googleapis.com/auth/calendar.calendarlist.readonly',
            'https://www.googleapis.com/auth/calendar.events',
            'https://www.googleapis.com/auth/calendar.events.freebusy',
        ],
    ],

    'microsoft' => [
        'client_id' => env('MICROSOFT_CALENDAR_CLIENT_ID'),
        'client_secret' => env('MICROSOFT_CALENDAR_CLIENT_SECRET'),
        'tenant' => env('MICROSOFT_CALENDAR_TENANT', 'common'),
        'redirect_uri' => env('MICROSOFT_CALENDAR_REDIRECT_URI', rtrim((string) env('APP_URL', 'http://localhost'), '/').'/calendar-connections/oauth/microsoft/callback'),
        'scopes' => ['openid', 'profile', 'email', 'offline_access', 'User.Read', 'Calendars.ReadWrite'],
    ],
];
