<?php

return [
    'public_hold_ttl_minutes' => (int) env('BOOKING_PUBLIC_HOLD_TTL_MINUTES', 15),
    'email_verification_ttl_hours' => (int) env('BOOKING_EMAIL_VERIFICATION_TTL_HOURS', 24),
    'schedule_proposal_default_ttl_hours' => (int) env('BOOKING_SCHEDULE_PROPOSAL_DEFAULT_TTL_HOURS', 24),
    'schedule_proposal_max_ttl_hours' => (int) env('BOOKING_SCHEDULE_PROPOSAL_MAX_TTL_HOURS', 168),
    'reference_length' => 12,
];
