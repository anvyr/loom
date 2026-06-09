<?php

declare(strict_types=1);

namespace Anvyr\Loom\Core;

use Anvyr\Loom\Core\Tenancy\TenancyState;
use Anvyr\Loom\Exceptions\ContainerException;
use Anvyr\Loom\Exceptions\ServiceNotFoundException;

/**
 * @phpstan-type Dependency array{type: 'class'|'default', value: mixed}
 * @phpstan-type ReflectionCache array{constructor: true|null, dependencies: list<Dependency>}
 */
class Application implements \Psr\Container\ContainerInterface
{
    private static ?Application $instance = null;

    public EventDispatcher $events;

    private Paths $paths;
    private TenancyState $tenancyState;

    /** @var array<string, callable(): mixed> */
    private array $services = [];

    /** @var array<string, mixed> */
    private array $instances = [];

    /** @var array<string, string> */
    private array $aliases = [];

    /** @var array<class-string, ReflectionCache> */
    private array $reflectionCache = [];

    /** @var list<ServiceProvider> */
    private array $providers = [];
    private bool $booted = false;

    public function __construct(
        string $basePath,
        ?ConfigRepository $configRepository = null,
        ?TenancyState $tenancyState = null,
    ) {
        $bootstrapPaths = new Paths($basePath);
        $this->tenancyState = $tenancyState ?? TenancyState::fromConfigFile($bootstrapPaths->config('tenancy.php'));
        $this->paths = new Paths($basePath, $this->tenancyState);
        $this->events = new EventDispatcher();

        $this->instance(self::class, $this);
        $this->instance(Application::class, $this);
        $this->instance('paths', $this->paths);
        $this->alias('paths', Paths::class);
        $this->instance(TenancyState::class, $this->tenancyState);
        $this->instance('tenancy.state', $this->tenancyState);

        self::setInstance($this);

        $configRepository ??= $this->createConfigRepository();
        $this->instance('config', $configRepository);
        $this->instance(ConfigRepository::class, $configRepository);

        $this->register(CoreServiceProvider::class);
    }

    public static function setInstance(Application $app): void
    {
        self::$instance = $app;
    }

    public static function getInstance(): Application
    {
        if (self::$instance === null) {
            throw new \RuntimeException('Application has not been set. Call Application::setInstance() during bootstrap.');
        }

        return self::$instance;
    }

    public static function hasInstance(): bool
    {
        return self::$instance !== null;
    }

    public static function clearInstance(): void
    {
        self::$instance = null;
    }

    /** @param class-string<ServiceProvider>|ServiceProvider $provider */
    public function register(string|ServiceProvider $provider): ServiceProvider
    {
        if (is_string($provider)) {
            $provider = new $provider($this);
        }

        $provider->register();

        $this->providers[] = $provider;

        if ($this->booted) {
            $provider->boot();
        }

        return $provider;
    }

    public function boot(): void
    {
        if ($this->booted) {
            return;
        }

        date_default_timezone_set(config('app.timezone', 'UTC'));

        $this->events->dispatch('app.booting', $this);

        foreach ($this->providers as $provider) {
            $provider->boot();
        }

        $this->events->dispatch('app.booted', $this);
        $this->booted = true;
    }

    public function bind(string $name, callable $factory): void
    {
        $this->services[$name] = $factory;
    }

    public function singleton(string $name, callable $factory): void
    {
        $this->services[$name] = function () use ($name, $factory) {
            if (!array_key_exists($name, $this->instances)) {
                $this->instances[$name] = $factory();
            }

            return $this->instances[$name];
        };
    }

    public function get(string $name): mixed
    {
        $key = $this->resolveAlias($name);

        if (!isset($this->services[$key])) {
            throw new ServiceNotFoundException("Service not found: {$name}");
        }

        return $this->services[$key]();
    }

    public function has(string $name): bool
    {
        $key = $this->resolveAlias($name);

        return isset($this->services[$key]);
    }

    private function resolveAlias(string $name): string
    {
        return $this->aliases[$name] ?? $name;
    }

    public function __get(string $name): mixed
    {
        return $this->get($name);
    }

    public function alias(string $key, string $alias): void
    {
        $this->aliases[$alias] = $key;
    }

    public function instance(string $name, mixed $instance): void
    {
        $this->instances[$name] = $instance;
        $this->services[$name] = fn () => $this->instances[$name];
    }

    public function make(string $name): mixed
    {
        if ($this->has($name)) {
            return $this->get($name);
        }

        if (class_exists($name)) {
            return $this->autowire($name);
        }

        throw new ServiceNotFoundException("Service not found: {$name}");
    }

    /** @param class-string $className */
    private function autowire(string $className): object
    {
        if (!isset($this->reflectionCache[$className])) {
            $this->reflectionCache[$className] = $this->buildReflectionCache($className);
        }

        $cached = $this->reflectionCache[$className];

        if ($cached['constructor'] === null) {
            return new $className();
        }

        $dependencies = [];
        foreach ($cached['dependencies'] as $dep) {
            if ($dep['type'] === 'class') {
                $dependencies[] = $this->make($dep['value']);
            } else {
                $dependencies[] = $dep['value'];
            }
        }

        return new $className(...$dependencies);
    }

    /**
     * @param class-string $className
     * @return ReflectionCache
     */
    private function buildReflectionCache(string $className): array
    {
        $reflection = new \ReflectionClass($className);

        if (!$reflection->isInstantiable()) {
            throw new ContainerException("Class {$className} is not instantiable");
        }

        $constructor = $reflection->getConstructor();

        if ($constructor === null) {
            return [
                'constructor' => null,
                'dependencies' => [],
            ];
        }

        $dependencies = [];

        foreach ($constructor->getParameters() as $parameter) {
            $type = $parameter->getType();

            if ($type instanceof \ReflectionNamedType && !$type->isBuiltin()) {
                $dependencies[] = ['type' => 'class', 'value' => $type->getName()];
            } elseif ($parameter->isDefaultValueAvailable()) {
                $dependencies[] = ['type' => 'default', 'value' => $parameter->getDefaultValue()];
            } elseif ($parameter->allowsNull()) {
                $dependencies[] = ['type' => 'default', 'value' => null];
            } else {
                throw new ContainerException(
                    "Cannot autowire {$className}: parameter \${$parameter->getName()} has no type hint or default value"
                );
            }
        }

        return [
            'constructor' => true,
            'dependencies' => $dependencies,
        ];
    }

    public function basePath(string $path = ''): string
    {
        return $this->paths->base($path);
    }

    private function createConfigRepository(): ConfigRepository
    {
        $tenantConfigPath = function (): ?string {
            if (!$this->tenancyState->isEnabled() || $this->tenancyState->currentId() === null) {
                return null;
            }

            return $this->paths->tenantUser('config');
        };

        return new ConfigRepository(
            $this->paths->config(),
            $this->paths->storage('cache/config.php'),
            $this->paths->user('config'),
            $tenantConfigPath,
        );
    }

    public function environment(): string
    {
        return (string) config('app.env', 'production');
    }

    public function isDebug(): bool
    {
        return (bool) config('app.debug', false);
    }

    public function registerDefaultRoutes(\Anvyr\Loom\Http\Routing\Router $router): void
    {
        $router->get('/', [\Anvyr\Loom\Http\Controllers\PageController::class, 'home']);
        $router->get('/{slug*}', [\Anvyr\Loom\Http\Controllers\PageController::class, 'show']);
    }
}
