<?php

declare(strict_types=1);

/**
 * Session Configuration
 * Anvyr Loom uses PHP's native session handling
 */
return [
    /** Session driver (currently uses PHP native) */
    'driver' => 'file',

    /** Session lifetime in seconds (default: 2 hours) */
    'lifetime' => (int) env('SESSION_LIFETIME', 7200),

    /** Session cookie name */
    'name' => env('SESSION_COOKIE', 'loom_session'),

    /** HTTP only cookies (prevents JavaScript access, XSS protection) */
    'http_only' => true,

    /** Secure cookies (only sent over HTTPS, auto-enabled in production) */
    'secure' => env('SESSION_SECURE_COOKIE', null),

    /** SameSite cookie attribute: Lax, Strict, or None */
    'same_site' => 'Lax',
];
