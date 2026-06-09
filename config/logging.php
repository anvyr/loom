<?php

declare(strict_types=1);

/**
 * Logging Configuration
 */
return [
    'path' => storage_path('logs/loom.log'),

    /** debug, info, notice, warning, error, critical, alert, emergency */
    'level' => env('LOG_LEVEL', 'info'),

    /** Use daily rotation (loom-2026-01-28.log) */
    'daily' => env('LOG_DAILY', false),

    /** Days to keep when daily=true */
    'max_files' => 14,
];
