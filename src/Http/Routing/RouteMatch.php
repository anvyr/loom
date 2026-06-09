<?php

declare(strict_types=1);

namespace Anvyr\Loom\Http\Routing;

final class RouteMatch
{
    /** @param array<string, string> $params */
    public function __construct(
        private Route $route,
        private array $params
    ) {
    }

    public function route(): Route
    {
        return $this->route;
    }

    /** @return array<string, string> */
    public function params(): array
    {
        return $this->params;
    }
}
