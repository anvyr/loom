<?php

declare(strict_types=1);

namespace Anvyr\Loom\Http\Routing;

/**
 * @phpstan-type ControllerAction array{0: class-string, 1: non-empty-string}
 * @phpstan-type RouteHandler callable|ControllerAction
 * @phpstan-type MiddlewareDefinition callable|string
 * @phpstan-type RouteData array{id: int, methods: list<string>, path: string, pattern: string, handler: RouteHandler, middleware?: list<MiddlewareDefinition>, name?: ?string}
 */
final class Route
{
    /** @var RouteHandler */
    private mixed $handler;

    /**
     * @param list<string> $methods
     * @param RouteHandler $handler
     * @param list<MiddlewareDefinition> $middleware
     */
    public function __construct(
        private int $id,
        private array $methods,
        private string $path,
        private string $pattern,
        callable|array $handler,
        private array $middleware = [],
        private ?string $name = null,
    ) {
        $this->handler = $handler;
    }

    public function id(): int
    {
        return $this->id;
    }

    /** @return list<string> */
    public function methods(): array
    {
        return $this->methods;
    }

    public function path(): string
    {
        return $this->path;
    }

    public function pattern(): string
    {
        return $this->pattern;
    }

    /** @return RouteHandler */
    public function handler(): callable|array
    {
        /** @var RouteHandler $handler */
        $handler = $this->handler;
        return $handler;
    }

    /** @return list<MiddlewareDefinition> */
    public function middleware(): array
    {
        return $this->middleware;
    }

    public function name(): ?string
    {
        return $this->name;
    }

    /** @param list<MiddlewareDefinition> $middleware */
    public function addMiddleware(array $middleware): void
    {
        $this->middleware = array_merge($this->middleware, $middleware);
    }

    /**
     * @return array{matched: bool, params: array<string, string>}
     */
    public function match(string $path): array
    {
        if (!preg_match($this->pattern, $path, $matches)) {
            return ['matched' => false, 'params' => []];
        }

        /** @var array<string, string> $params */
        $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);

        return ['matched' => true, 'params' => $params];
    }

    /**
     * @return RouteData
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'methods' => $this->methods,
            'path' => $this->path,
            'pattern' => $this->pattern,
            'handler' => $this->handler(),
            'middleware' => $this->middleware,
            'name' => $this->name,
        ];
    }

    /**
     * @param RouteData $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            $data['id'],
            array_map('strtoupper', $data['methods']),
            $data['path'],
            $data['pattern'],
            $data['handler'],
            $data['middleware'] ?? [],
            $data['name'] ?? null,
        );
    }
}
