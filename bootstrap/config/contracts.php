<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Contract storage
    |--------------------------------------------------------------------------
    |
    | Contract templates and signed copies are private documents. The default
    | local disk already points at storage/app/private. They must be served
    | through authorized application routes, never a public storage symlink.
    |
    */
    'disk' => env('CONTRACT_FILESYSTEM_DISK', 'local'),

    'template_directory' => 'contracts/templates',
    'signed_directory' => 'contracts/signed',

    // Laravel file-validation sizes are expressed in KiB.
    'max_template_kilobytes' => (int) env('CONTRACT_TEMPLATE_MAX_KB', 20480),
    'max_signed_file_kilobytes' => (int) env('CONTRACT_SIGNED_FILE_MAX_KB', 20480),
    'max_signed_files' => (int) env('CONTRACT_SIGNED_MAX_FILES', 30),

    // No HTML/SVG/executable formats. Signed-copy validation is used in M4.
    'template_extensions' => ['pdf', 'doc', 'docx', 'odt', 'jpg', 'jpeg', 'png', 'webp'],
    'signed_extensions' => ['pdf', 'jpg', 'jpeg', 'png', 'webp'],
];
