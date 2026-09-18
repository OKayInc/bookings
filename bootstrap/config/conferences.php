<?php

return [
    'http_timeout_seconds' => (int) env('CONFERENCE_HTTP_TIMEOUT_SECONDS', 10),
    'jitsi_base_url' => rtrim((string) (env('JITSI_BASE_URL') ?: 'https://meet.jit.si'), '/'),
    'google_token_url' => 'https://oauth2.googleapis.com/token',
    'google_spaces_url' => 'https://meet.googleapis.com/v2/spaces',
    'microsoft_authority' => 'https://login.microsoftonline.com',
    'microsoft_graph_url' => 'https://graph.microsoft.com/v1.0',
    'zoom_token_url' => 'https://zoom.us/oauth/token',
    'zoom_api_url' => 'https://api.zoom.us/v2',
    'webex_token_url' => 'https://webexapis.com/v1/access_token',
    'webex_api_url' => 'https://webexapis.com/v1',
];
