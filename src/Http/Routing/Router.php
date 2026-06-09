<?php

declare(strict_types=1);

namespace Anvyr\Loom\Http\Routing;

use Anvyr\Loom\Core\Application;
use Anvyr\Loom\Core\EventDispatcher;
use Anvyr\Loom\Http\Middleware\Pipeline;
use Anvyr\Loom\Http\Request;
use Anvyr\Loom\Http\Response;

/**
 * @phpstan-import-type RouteData from Route
 * @phpstan-import-type RouteHandler from Route
 * @phpstan-import-type MiddlewareDefinition from Route
 */
class Router
{
    /** @var array<string, list<int>> */
    private array $routes = [];

    /** @var array<string, int> */
    private array $namedRoutes = [];

    /** @var array<string, MiddlewareDefinition> */
    private array $middlewareAliases = [];

    /** @var list<MiddlewareDefinition> */
    private array $globalMiddleware = [];
    private ?Application $app = null;

    /** @var array<int, Route> */
    private array $routesById = [];

    private int $routeCounter = 0;
    private ?int $lastRouteId = null;

    /** @var list<array{prefix: string, middleware: list<MiddlewareDefinition>}> */
    private array $groupStack = [];

    public function __construct(
        private readonly EventDispatcher $events
    ) {
    }

    public function setApp(Application $app): void
    {
        $this->app = $app;
    }

    /** @param RouteHandler $handler */
    public function get(string $path, callable|array $handler, ?string $name = null): RouteDefinition
    {
        return $this->addRoute('GET', $path, $handler, $name);
    }

    /** @param RouteHandler $handler */
    public function post(string $path, callable|array $handler, ?string $name = null): RouteDefinition
    {
        return $this->addRoute('POST', $path, $handler, $name);
    }

    /** @param RouteHandler $handler */
    public function put(string $path, callable|array $handler, ?string $name = null): RouteDefinition
    {
        return $this->addRoute('PUT', $path, $handler, $name);
    }

    /** @param RouteHandler $handler */
    public function delete(string $path, callable|array $handler, ?string $name = null): RouteDefinition
    {
        return $this->addRoute('DELETE', $path, $handler, $name);
    }

    /** @param RouteHandler $handler */
    public function patch(string $path, callable|array $handler, ?string $name = null): RouteDefinition
    {
        return $this->addRoute('PATCH', $path, $handler, $name);
    }

    /** @param RouteHandler $handler */
    public function any(string $path, callable|array $handler, ?string $name = null): RouteDefinition
    {
        return $this->addRoute(['GET', 'POST', 'PUT', 'DELETE', 'PATCH'], $path, $handler, $name);
    }

    /** @param array{prefix?: string, middleware?: MiddlewareDefinition|list<MiddlewareDefinition>} $attributes */
    public function group(array $attributes, callable $callback): void
    {
        $this->groupStack[] = [
            'prefix' => $attributes['prefix'] ?? '',
            'middleware' => isset($attributes['middleware'])
                ? $this->normalizeMiddlewareList($attributes['middleware'])
                : [],
        ];

        $callback($this);

        array_pop($this->groupStack);
    }

    private function resolveGroupPrefix(): string
    {
        $prefix = '';
        foreach ($this->groupStack as $group) {
            if ($group['prefix'] !== '') {
                $prefix .= '/' . trim($group['prefix'], '/');
            }
        }
        return $prefix;
    }

    /** @return list<MiddlewareDefinition> */
    private function resolveGroupMiddleware(): array
    {
        $middleware = [];
        foreach ($this->groupStack as $group) {
            $middleware = array_merge($middleware, $group['middleware']);
        }
        return $middleware;
    }

    /**
     * @param string|list<string> $methods
     * @param RouteHandler $handler
     */
    private function addRoute(string|array $methods, string $path, callable|array $handler, ?string $name = null): RouteDefinition
    {
        $methods = array_map('strtoupper', (array) $methods);

        $prefix = $this->resolveGroupPrefix();
        $path = $prefix . '/' . ltrim($path, '/');

        $pattern = $this->compilePattern($path);
        $groupMiddleware = $this->resolveGroupMiddleware();

        $routeId = ++$this->routeCounter;

        $route = new Route(
            id: $routeId,
            methods: $methods,
            path: $path,
            pattern: $pattern,
            handler: $handler,
            middleware: $groupMiddleware,
            name: $name,
        );

        $this->routesById[$routeId] = $route;

        foreach ($methods as $method) {
            $this->routes[$method][] = $routeId;
        }

        if ($name !== null) {
            $this->namedRoutes[$name] = $routeId;
        }

        $this->lastRouteId = $routeId;

        return new RouteDefinition($this, $routeId);
    }

    private function compilePattern(string $path): string
    {
        $placeholders = [];

        $tokenizedPath = preg_replace_callback('/\{(\w+)(\*|\?)?\}/', function (array $matches) use (&$placeholders): string {
            $name = $matches[1];
            $modifier = $matches[2] ?? '';

            $replacement = match ($modifier) {
                '*' => '(?P<' . $name . '>.+)',
                '?' => '(?P<' . $name . '>[^/]*)',
                default => '(?P<' . $name . '>[^/]+)',
            };

            $token = '__VLT_PARAM_' . count($placeholders) . '__';
            $placeholders[$token] = $replacement;

            return $token;
        }, $path);

        if ($tokenizedPath === null) {
            throw new \RuntimeException("Unable to compile route pattern [{$path}].");
        }

        $escapedPath = preg_quote($tokenizedPath, '#');

        foreach ($placeholders as $token => $replacement) {
            $escapedPath = str_replace(preg_quote($token, '#'), $replacement, $escapedPath);
        }

        return '#^' . $escapedPath . '$#';
    }

    /** @param MiddlewareDefinition|list<MiddlewareDefinition> $middleware */
    public function middleware(string|array|callable $middleware): self
    {
        if ($this->lastRouteId === null) {
            throw new \RuntimeException('No route available to attach middleware to.');
        }

        $middlewareList = $this->normalizeMiddlewareList($middleware);
        $this->attachRouteMiddleware($this->lastRouteId, $middlewareList);

        return $this;
    }

    /** @param list<MiddlewareDefinition> $middleware */
    public function attachRouteMiddleware(int $routeId, array $middleware): void
    {
        if (!isset($this->routesById[$routeId])) {
            throw new \InvalidArgumentException("Route not found: {$routeId}");
        }

        $this->routesById[$routeId]->addMiddleware($middleware);
    }

    /**
     * @param MiddlewareDefinition|list<MiddlewareDefinition> $middleware
     * @return list<MiddlewareDefinition>
     */
    private function normalizeMiddlewareList(string|array|callable $middleware): array
    {
        return is_array($middleware) ? array_values($middleware) : [$middleware];
    }

    public function dispatch(Request $request): Response
    {
        $method = $request->method();
        $path = $request->path();

        $originalMethod = $method;

        // Check for method spoofing
        if ($method === 'POST' && $request->has('_method')) {
            $method = strtoupper($request->input('_method'));
        }

        // Fire routing event with the resolved method
        $this->events->dispatch('router.matching', [
            'method' => $method,
            'path' => $path,
            'original_method' => $originalMethod,
        ]);

        // Find matching route
        // Support HEAD requests by falling back to GET routes
        $methodsToSearch = [$method];
        if ($method === 'HEAD') {
            $methodsToSearch[] = 'GET';
        }

        foreach ($methodsToSearch as $searchMethod) {
            if (isset($this->routes[$searchMethod])) {
                foreach ($this->routes[$searchMethod] as $routeId) {
                    $route = $this->routesById[$routeId] ?? null;
                    if ($route === null) {
                        continue;
                    }

                    $result = $route->match($path);
                    if (!$result['matched']) {
                        continue;
                    }

                    $match = new RouteMatch($route, $result['params']);

                    // Fire matched event
                    $this->events->dispatch('router.matched', ['route' => $route->toArray(), 'params' => $match->params()]);

                    $pipeline = new Pipeline($this->app);

                    $middlewareStack = array_merge(
                        $this->globalMiddleware,
                        $match->route()->middleware()
                    );

                    $response = $pipeline
                        ->withAliases($this->middlewareAliases)
                        ->send($request)
                        ->through($middlewareStack)
                        ->then(function (Request $request) use ($match): Response {
                            return $this->executeHandler($match->route()->handler(), $match->params(), $request);
                        });

                    return $response;
                }
            }
        }

        $allowedMethods = [];

        foreach ($this->routesById as $route) {
            if (!$route->match($path)['matched']) {
                continue;
            }

            $allowedMethods = array_merge($allowedMethods, $route->methods());
        }

        if ($allowedMethods !== [] && !in_array($method, $allowedMethods, true)) {
            $allowedMethods = array_values(array_unique($allowedMethods));
            sort($allowedMethods);

            return Response::methodNotAllowed($allowedMethods);
        }

        return $this->notFound();
    }

    /**
     * @param RouteHandler $handler
     * @param array<string, string> $params
     */
    private function executeHandler(callable|array $handler, array $params, Request $request): Response
    {
        // Controller action
        if (is_array($handler)) {
            [$controller, $method] = $handler;

            // Use Application container to resolve dependencies if available
            if ($this->app) {
                $instance = $this->app->make($controller);
            } else {
                $instance = new $controller();
            }

            $result = $instance->$method($request, ...array_values($params));
        } else {
            // Closure - pass request first, then route params
            $result = call_user_func_array($handler, [$request, ...array_values($params)]);
        }

        // Convert to Response if needed
        if (!$result instanceof Response) {
            if (is_string($result)) {
                $result = Response::html($result);
            } elseif (is_array($result)) {
                $result = Response::json($result);
            } else {
                $result = Response::html((string) $result);
            }
        }

        return $result;
    }

    public function registerMiddleware(string $name, callable|string $middleware): void
    {
        $this->middlewareAliases[$name] = $middleware;
    }

    public function pushMiddleware(callable|string $middleware): void
    {
        $this->globalMiddleware[] = $middleware;
    }

    /** @param array<string, scalar|\Stringable> $params */
    public function url(string $name, array $params = []): string
    {
        if (!isset($this->namedRoutes[$name])) {
            throw new \RuntimeException("Route not found: {$name}");
        }

        $routeId = $this->namedRoutes[$name];

        if (!isset($this->routesById[$routeId])) {
            throw new \RuntimeException("Route not found: {$name}");
        }

        $path = $this->routesById[$routeId]->path();

        foreach ($params as $key => $value) {
            $stringValue = (string) $value;
            $path = str_replace('{' . $key . '}', $stringValue, $path);
            $path = str_replace('{' . $key . '?}', $stringValue, $path);
        }

        // Strip unfilled optional parameters
        $path = preg_replace('#/\{[^}]+\?\}#', '', $path) ?? $path;

        return $path;
    }

    private function notFound(): Response
    {
        return Response::notFound('404 Not Found');
    }

    /** @return array<int, RouteData> */
    public function getRouteDefinitions(): array
    {
        $definitions = [];

        foreach ($this->routesById as $id => $route) {
            $definitions[$id] = $route->toArray();
        }

        return $definitions;
    }

    /** @param list<RouteData> $cachedRoutes */
    public function loadCachedRoutes(array $cachedRoutes): void
    {
        foreach ($cachedRoutes as $route) {
            $routeObject = Route::fromArray($route);
            $this->routesById[$routeObject->id()] = $routeObject;

            foreach ($routeObject->methods() as $method) {
                $this->routes[$method][] = $routeObject->id();
            }

            if ($routeObject->name() !== null) {
                $this->namedRoutes[$routeObject->name()] = $routeObject->id();
            }

            if ($routeObject->id() > $this->routeCounter) {
                $this->routeCounter = $routeObject->id();
            }
        }
    }

    public function hasCachedRoutes(): bool
    {
        return file_exists(storage_path('cache/routes.php'));
    }
}
