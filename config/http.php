<?php

declare(strict_types=1);

/**
 * HTTP Configuration
 */
return [
    /** Middleware configuration */
    'middleware' => [
        /** Middleware aliases */
        'aliases' => [
            'errors' => \Anvyr\Loom\Http\Middleware\ErrorHandlingMiddleware::class,
            'throttle' => \Anvyr\Loom\Http\Middleware\ThrottleRequests::class,
            'csrf' => \Anvyr\Loom\Http\Middleware\VerifyCsrfToken::class,
            'session' => \Anvyr\Loom\Http\Middleware\StartSessionMiddleware::class,
            'cache' => \Anvyr\Loom\Http\Middleware\CacheResponse::class,
        ],

        /** Global middleware stack */
        'global' => [
            'errors',
            'session',
            'throttle',
        ],
    ],

    /** Rate limiting */
    'rate_limit' => [
        'enabled' => true,
        'default' => 'standard',

        'limiters' => [
            'standard' => ['attempts' => 60, 'decay' => 60, 'by' => 'ip'],
            'api' => ['attempts' => 120, 'decay' => 60, 'by' => 'ip'],
            'auth' => ['attempts' => 5, 'decay' => 60, 'by' => 'ip'],
            'strict' => ['attempts' => 10, 'decay' => 60, 'by' => 'ip'],
        ],

        'whitelist' => ['127.0.0.1', '::1'],
    ],

    /** Trusted reverse proxy settings */
    'trusted_proxies' => [
        'enabled' => false,
        'proxies' => [],
        'headers' => [
            'for' => 'X-Forwarded-For',
            'proto' => 'X-Forwarded-Proto',
            'host' => 'X-Forwarded-Host',
        ],
    ],
];
