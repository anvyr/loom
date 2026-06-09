<?php

declare(strict_types=1);

namespace Anvyr\Loom\Tests\Support\Doubles\Http;

use Anvyr\Loom\Contracts\MiddlewareInterface;
use Anvyr\Loom\Http\Request;
use Anvyr\Loom\Http\Response;

final class RecordingMiddleware implements MiddlewareInterface
{
    public static array $calls = [];

    public function handle(Request $request, callable $next): Response
    {
        self::$calls[] = 'before';
        $response = $next($request);
        self::$calls[] = 'after';

        return $response;
    }
}
