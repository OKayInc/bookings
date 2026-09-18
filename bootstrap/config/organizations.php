<?php

return [
    'logo_disk' => env('ORGANIZATION_LOGO_DISK', 'public'),
    'logo_directory' => 'organizations/logos',
    'logo_extensions' => ['jpg', 'jpeg', 'png', 'webp'],
    'max_logo_kilobytes' => 5120,
    'member_invitation_ttl_days' => (int) env('ORGANIZATION_MEMBER_INVITATION_TTL_DAYS', 7),
];
