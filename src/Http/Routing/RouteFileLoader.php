<?php

declare(strict_types=1);

namespace Anvyr\Loom\Http\Routing;

use Anvyr\Loom\Core\Application;
use Closure;

final class RouteFileLoader
{
    public static function register(string $path, Router $router, Application $app): void
    {
        $registrar = self::load($path);
        $registrar($router, $app);
    }

    private static function load(string $path): Closure
    {
        try {
            $registrar = require $path;
        } catch (\Throwable $exception) {
            throw new \RuntimeException(
                "Failed loading route file '{$path}'. Route files must return "
                . 'static function (Router $router, Application $app): void { ... }.',
                0,
                $exception,
            );
        }

        if (!$registrar instanceof Closure) {
            throw new \RuntimeException(
                "Route file '{$path}' must return "
                . 'static function (Router $router, Application $app): void { ... }.'
            );
        }

        return $registrar;
    }
}
