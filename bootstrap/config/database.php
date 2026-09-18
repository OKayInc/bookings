<?php

use Illuminate\Support\Str;

return [
    'default' => env('DB_CONNECTION', 'mariadb'),
    'connections' => [
        'sqlite' => [
            'driver' => 'sqlite',
            'url' => env('DB_URL'),
            'database' => env('DB_DATABASE', database_path('database.sqlite')),
            'prefix' => '',
            'foreign_key_constraints' => env('DB_FOREIGN_KEYS', true),
            'busy_timeout' => null,
            'journal_mode' => null,
            'synchronous' => null,
        ],
        'mariadb' => [
            'driver' => 'mariadb',
            'url' => env('DB_URL'),
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '3306'),
            'database' => env('DB_DATABASE', 'appointment'),
            'username' => env('DB_USERNAME', 'appointment'),
            'password' => env('DB_PASSWORD', ''),
            'unix_socket' => env('DB_SOCKET', ''),
            'charset' => env('DB_CHARSET', 'utf8mb4'),
            'collation' => env('DB_COLLATION', 'utf8mb4_unicode_ci'),
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
            'timezone' => env('DB_TIMEZONE', '+00:00'),
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                PDO::ATTR_EMULATE_PREPARES => false,
            ]) : [],
        ],
        'mariadb_testing' => [
            'driver' => 'mariadb',
            'url' => env('TEST_DB_URL'),
            'host' => env('TEST_DB_HOST', env('DB_HOST', '127.0.0.1')),
            'port' => env('TEST_DB_PORT', env('DB_PORT', '3306')),
            'database' => env('TEST_DB_DATABASE', 'appointment_testing'),
            'username' => env('TEST_DB_USERNAME', env('DB_USERNAME', 'appointment')),
            'password' => env('TEST_DB_PASSWORD', env('DB_PASSWORD', '')),
            'unix_socket' => env('TEST_DB_SOCKET', env('DB_SOCKET', '')),
            'charset' => env('TEST_DB_CHARSET', env('DB_CHARSET', 'utf8mb4')),
            'collation' => env('TEST_DB_COLLATION', env('DB_COLLATION', 'utf8mb4_unicode_ci')),
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
            'timezone' => env('TEST_DB_TIMEZONE', env('DB_TIMEZONE', '+00:00')),
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                PDO::ATTR_EMULATE_PREPARES => false,
            ]) : [],
        ],
    ],
    'migrations' => [
        'table' => 'migrations',
        'update_date_on_publish' => true,
    ],
];
