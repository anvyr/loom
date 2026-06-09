<?php

declare(strict_types=1);

/**
 * Exception Handling Configuration
 */
return [
    /**
     * Custom exception renderers
     * Maps exception classes to render functions
     * Example: Throwable::class => fn (Throwable $e, Request $request): Response => ...
     */
    'renderers' => [],

    /**
     * Custom exception reporters
     * Maps exception classes to reporting functions
     * Example: Throwable::class => fn (Throwable $e, Request $request, LoggerInterface $logger): void => ...
     */
    'reporters' => [],
];
