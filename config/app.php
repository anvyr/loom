<?php

declare(strict_types=1);

/**
 * Application Configuration
 */
return [
    /** Application name */
    'name' => env('APP_NAME', 'Anvyr Loom'),

    /** Application URL (without trailing slash) */
    'url' => env('APP_URL', 'http://localhost'),

    /** Environment label (local or production). Any non-production value is treated as local. */
    'env' => env('APP_ENV', 'production'),

    /** Enable debug mode (detailed HTML/JSON errors). Defaults to true outside production. */
    'debug' => env('APP_DEBUG', env('APP_ENV', 'production') !== 'production'),

    /** Logging level: debug, info, notice, warning, error, critical, alert, emergency */
    'log_level' => env('APP_LOG_LEVEL', 'info'),

    /** Default timezone */
    'timezone' => 'UTC',

    /** Default locale */
    'locale' => 'en',

    /** Enable optional WebCron endpoint registration */
    'cron_enabled' => (bool) env('CRON_ENABLED', false),

    /** Shared token for WebCron authorization */
    'cron_token' => env('CRON_TOKEN', ''),

    /** Optional WebCron request signing using expires/signature query params */
    'cron_signed_urls' => (bool) env('CRON_SIGNED_URLS', false),

    /** Optional WebCron source IP allowlist (exact, CIDR, or *) */
    'cron_allowed_ips' => [],

    /** Optional WebCron rate limiting */
    'cron_rate_limit' => [
        'enabled' => false,
        'attempts' => 60,
        'decay' => 60,
    ],
];
