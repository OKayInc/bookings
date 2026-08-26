<?php

return [
    'file_disk' => env('QUESTIONNAIRE_FILE_DISK', 'local'),
    'max_text_length' => (int) env('QUESTIONNAIRE_MAX_TEXT_LENGTH', 20000),
    'max_file_kilobytes' => (int) env('QUESTIONNAIRE_MAX_FILE_KILOBYTES', 20480),
    'max_files_per_question' => (int) env('QUESTIONNAIRE_MAX_FILES_PER_QUESTION', 20),
    'file_extensions' => ['pdf','jpg','jpeg','png','webp','doc','docx','txt'],
    'default_phone_region' => env('QUESTIONNAIRE_DEFAULT_PHONE_REGION', 'CA'),
    'email_dns_validation' => env('QUESTIONNAIRE_EMAIL_DNS_VALIDATION', true),
    'google' => [
        'api_key' => env('GOOGLE_MAPS_API_KEY'),
        'address_validation_url' => env('GOOGLE_ADDRESS_VALIDATION_URL', 'https://addressvalidation.googleapis.com/v1:validateAddress'),
        'timeout_seconds' => (int) env('GOOGLE_ADDRESS_VALIDATION_TIMEOUT', 8),
    ],
];
