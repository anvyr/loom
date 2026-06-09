<?php

declare(strict_types=1);

/**
 * View Configuration
 */
return [
    /** User views directory (relative to base_path) */
    'path' => 'user/views',

    /** Compiled views cache directory (relative to storage_path) */
    'compiled' => 'cache/views',

    /** Allow runtime string template evaluation in compileString/safe */
    'allow_string_evaluation' => true,
];
