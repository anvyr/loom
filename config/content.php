<?php

declare(strict_types=1);

/**
 * Content Configuration
 */
return [
    /** File-native page content configuration */
    'drivers' => [
        'file' => [
            'path' => content_path('pages'),
            'index' => [
                'driver' => env('CONTENT_PAGE_INDEX_DRIVER', 'json'),
                'json' => [
                    'path' => storage_path('index/page-index.json'),
                ],
                'sqlite' => [
                    'path' => storage_path('index/page-index.sqlite'),
                ],
            ],
            'cache_enabled' => true,
            'cache_ttl' => 600,
        ],
    ],

    /** Content parsing configuration */
    'parser' => [
        /**
         * Parser driver: commonmark, parsedown, html
         * Custom drivers can be bound to Anvyr\Loom\Contracts\ParserInterface
         */
        'driver' => env('CONTENT_PARSER_DRIVER', 'commonmark'),

        /** Parsed content cache TTL in seconds (0 = disabled) */
        'cache_ttl' => 600,

        /** Driver-specific configurations */
        'drivers' => [
            'commonmark' => [
                /** Allow raw HTML in markdown */
                'html_input' => 'allow',
                /** CommonMark extensions */
                'extensions' => [
                    'table' => true,
                    'strikethrough' => true,
                    'autolink' => true,
                    'task_lists' => true,
                ],
            ],

            'parsedown' => [
                /** strip = safe mode */
                'html_input' => 'allow',
                'breaks' => true,
            ],

            'html' => [],
        ],
    ],
];
