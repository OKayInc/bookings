<?php

$applicationUrl = rtrim((string) env('APP_URL', 'http://localhost'), '/');
$cdnUrl = rtrim((string) env('CDN_URL', ''), '/');
$cdnEnabled = (bool) env('CDN_ENABLED', false) && $cdnUrl !== '';
$publicAssetUrl = $cdnEnabled ? $cdnUrl : $applicationUrl;
$publicStorageUrl = $cdnEnabled
    ? $cdnUrl.'/storage'
    : $applicationUrl.'/storage';

return [
    'default' => env('FILESYSTEM_DISK', 'local'),
    'public_asset_url' => $publicAssetUrl,
    'disks' => [
        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => true,
            'throw' => false,
        ],
        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => $publicStorageUrl,
            'visibility' => 'public',
            'throw' => false,
        ],
    ],
    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],
];
