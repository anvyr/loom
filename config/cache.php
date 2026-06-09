<?php

declare(strict_types=1);

/**
 * Cache Configuration
 */
return [
    /** Default cache driver */
    'default' => env('CACHE_DRIVER', 'file'),

    /** Cache driver configurations */
    'drivers' => [
        'file' => [
            'path' => storage_path('cache'),
        ],

        'redis' => [
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'port' => env('REDIS_PORT', 6379),
            'password' => env('REDIS_PASSWORD', null),
            'database' => 0,
        ],

        'apcu' => [
            // APCu uses shared memory, no path configuration needed
        ],
    ],

    /** Cache key prefix */
    'prefix' => 'loom',
];
