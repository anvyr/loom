<?php

declare(strict_types=1);

namespace Anvyr\Loom\Http\Middleware;

use Anvyr\Loom\Contracts\MiddlewareInterface;
use Anvyr\Loom\Http\Request;
use Anvyr\Loom\Http\Response;

/**
 * CSRF Protection Middleware
 *
 * Verifies CSRF tokens on state-changing requests (POST, PUT, DELETE, PATCH).
 * Tokens are stored in session and must match the submitted token.
 */
class VerifyCsrfToken implements MiddlewareInterface
{
    /** @var list<string> */
    private array $exceptions = [];
    private const METHODS = ['POST', 'PUT', 'DELETE', 'PATCH'];

    /** @param list<string> $exceptions */
    public function __construct(array $exceptions = [])
    {
        $this->exceptions = $exceptions;
    }

    public function handle(Request $request, callable $next): Response
    {
        // Skip CSRF check for excluded routes
        if ($this->shouldExclude($request)) {
            return $next($request);
        }

        // Only check state-changing methods
        if (in_array($request->method(), self::METHODS)) {
            if (!$this->tokensMatch($request)) {
                return $this->tokenMismatchResponse();
            }
        }

        return $next($request);
    }

    private function tokensMatch(Request $request): bool
    {
        $token = $this->getTokenFromRequest($request);
        $sessionToken = csrf_token();

        if (!$token || !$sessionToken) {
            return false;
        }

        return hash_equals($sessionToken, $token);
    }

    private function getTokenFromRequest(Request $request): ?string
    {
        // Check POST body
        $token = $request->input('_token');

        // Check headers (for AJAX requests)
        if (!$token) {
            $token = $request->header('X-CSRF-TOKEN');
        }

        if (!$token) {
            $token = $request->header('X-XSRF-TOKEN');
        }

        return $token;
    }

    private function shouldExclude(Request $request): bool
    {
        $path = $request->path();

        foreach ($this->exceptions as $pattern) {
            $regex = '#^' . str_replace(['*', '/'], ['.*', '\/'], $pattern) . '$#';

            if (preg_match($regex, $path)) {
                return true;
            }
        }

        return false;
    }

    private function tokenMismatchResponse(): Response
    {
        return Response::error('CSRF token mismatch. Please refresh the page and try again.', 419);
    }
}
