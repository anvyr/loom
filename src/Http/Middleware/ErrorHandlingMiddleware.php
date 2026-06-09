<?php

declare(strict_types=1);

namespace Anvyr\Loom\Http\Middleware;

use Anvyr\Loom\Contracts\MiddlewareInterface;
use Anvyr\Loom\Exceptions\Handler;
use Anvyr\Loom\Http\Request;
use Anvyr\Loom\Http\Response;
use Throwable;

final class ErrorHandlingMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly Handler $handler,
    ) {
    }

    public function handle(Request $request, callable $next): Response
    {
        try {
            return $next($request);
        } catch (Throwable $e) {
            $this->handler->report($e, $request);
            return $this->handler->render($e, $request);
        }
    }
}
