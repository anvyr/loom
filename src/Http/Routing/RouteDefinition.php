<?php

declare(strict_types=1);

namespace Anvyr\Loom\Http\Routing;

/**
 * @phpstan-import-type MiddlewareDefinition from Route
 *
 * Fluent builder for additional route metadata
 */
class RouteDefinition
{
    public function __construct(
        private readonly Router $router,
        private readonly int $routeId
    ) {
    }

    /** @param MiddlewareDefinition|list<MiddlewareDefinition> $middleware */
    public function middleware(string|array|callable $middleware): self
    {
        $middlewareList = is_array($middleware) ? array_values($middleware) : [$middleware];
        $this->router->attachRouteMiddleware($this->routeId, $middlewareList);

        return $this;
    }

    public function getRouteId(): int
    {
        return $this->routeId;
    }
}
