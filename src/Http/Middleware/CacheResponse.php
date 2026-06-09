<?php

declare(strict_types=1);

namespace Anvyr\Loom\Http\Middleware;

use Anvyr\Loom\Contracts\MiddlewareInterface;
use Anvyr\Loom\Http\Request;
use Anvyr\Loom\Http\Response;

/**
 * Generates ETag headers and returns 304 Not Modified when content is unchanged.
 * Optionally sets Cache-Control for cacheable GET responses.
 *
 * Usage: apply to routes via ->middleware('cache') or add to route groups.
 * Does not override Cache-Control headers already set by the controller.
 */
final class CacheResponse implements MiddlewareInterface
{
    public function __construct(
        private readonly int $maxAge = 0,
        private readonly bool $public = false,
    ) {
    }

    public function handle(Request $request, callable $next): Response
    {
        $response = $next($request);

        if (!$this->isCacheable($request, $response)) {
            return $response;
        }

        $etag = $this->generateEtag($response);
        $response->header('ETag', $etag);

        if ($response->getHeader('Cache-Control') === null) {
            $visibility = $this->public ? 'public' : 'private';
            $response->header('Cache-Control', "{$visibility}, max-age={$this->maxAge}");
        }

        if ($this->isNotModified($request, $etag)) {
            return (new Response('', 304))->header('ETag', $etag);
        }

        return $response;
    }

    private function isCacheable(Request $request, Response $response): bool
    {
        if ($request->method() !== 'GET' && $request->method() !== 'HEAD') {
            return false;
        }

        $status = $response->getStatus();

        return $status >= 200 && $status < 300;
    }

    private function generateEtag(Response $response): string
    {
        return '"' . md5($response->getContent()) . '"';
    }

    private function isNotModified(Request $request, string $etag): bool
    {
        $ifNoneMatch = $request->header('If-None-Match');

        if (!is_string($ifNoneMatch)) {
            return false;
        }

        // Handle multiple ETags: If-None-Match: "abc", "def"
        $clientTags = array_map('trim', explode(',', $ifNoneMatch));

        foreach ($clientTags as $tag) {
            if ($tag === $etag || $tag === '*') {
                return true;
            }
        }

        return false;
    }
}
