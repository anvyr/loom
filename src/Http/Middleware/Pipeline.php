<?php

declare(strict_types=1);

namespace Anvyr\Loom\Http\Middleware;

use Anvyr\Loom\Contracts\MiddlewareInterface;
use Anvyr\Loom\Core\Application;
use Anvyr\Loom\Http\Request;
use Anvyr\Loom\Http\Response;
use Closure;
use InvalidArgumentException;

class Pipeline
{
    private Request $passable;

    /** @var list<callable|string|object> */
    private array $pipes = [];

    /** @var array<string, callable|string|object> */
    private array $aliases = [];

    public function __construct(
        private readonly ?Application $app = null
    ) {
    }

    public function send(Request $request): self
    {
        $this->passable = $request;
        return $this;
    }

    /** @param list<callable|string|object> $pipes */
    public function through(array $pipes): self
    {
        $this->pipes = $pipes;
        return $this;
    }

    /** @param array<string, callable|string|object> $aliases */
    public function withAliases(array $aliases): self
    {
        $this->aliases = $aliases;
        return $this;
    }

    public function then(Closure $destination): Response
    {
        $pipeline = array_reduce(
            array_reverse($this->pipes),
            function (Closure $stack, mixed $pipe): Closure {
                return function (Request $request) use ($stack, $pipe): Response {
                    $middleware = $this->resolve($pipe);
                    return $middleware($request, $stack);
                };
            },
            function (Request $request) use ($destination): Response {
                $result = $destination($request);
                return $result instanceof Response ? $result : Response::html((string) $result);
            }
        );

        return $pipeline($this->passable);
    }

    private function resolve(mixed $pipe): callable
    {
        if (is_string($pipe) && isset($this->aliases[$pipe])) {
            $pipe = $this->aliases[$pipe];
        }

        if (is_string($pipe) && class_exists($pipe)) {
            $instance = $this->app ? $this->app->make($pipe) : new $pipe();
            return $this->wrap($instance);
        }

        if (is_object($pipe)) {
            return $this->wrap($pipe);
        }

        if (is_callable($pipe)) {
            return $this->standardizeCallable($pipe);
        }

        throw new InvalidArgumentException('Invalid middleware declaration.');
    }

    private function wrap(object $instance): callable
    {
        if ($instance instanceof MiddlewareInterface) {
            return fn (Request $request, callable $next): Response => $instance->handle($request, $next);
        }

        if (is_callable($instance)) {
            return $this->standardizeCallable($instance);
        }

        throw new InvalidArgumentException(sprintf('Middleware %s must be invokable or implement MiddlewareInterface.', $instance::class));
    }

    private function standardizeCallable(callable $middleware): callable
    {
        $callable = Closure::fromCallable($middleware);
        $reflection = new \ReflectionFunction($callable);

        if ($reflection->getNumberOfParameters() >= 2) {
            return function (Request $request, callable $next) use ($middleware): Response {
                $response = $middleware($request, $next);
                if ($response instanceof Response) {
                    return $response;
                }

                return $next($request);
            };
        }

        return function (Request $request, callable $next) use ($middleware): Response {
            $response = $middleware($request);
            if ($response instanceof Response) {
                return $response;
            }

            return $next($request);
        };
    }
}
