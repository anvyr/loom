<?php

declare(strict_types=1);

namespace Anvyr\Loom\Core;

use Anvyr\Loom\Core\Tenancy\ModuleArtifactPaths;
use Anvyr\Loom\Core\Tenancy\TenancyState;
use Anvyr\Loom\Core\Tenancy\TenantDiscovery;
use Anvyr\Loom\Database\Schema\Schema;
use Anvyr\Loom\Http\AssetServer;
use Anvyr\Loom\Http\Client\HttpClient;
use Anvyr\Loom\Http\Routing\Router;
use Anvyr\Loom\Validation\ValidationExtensionRegistry;

class CoreServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton('events', fn () => $this->app->events);
        $this->app->alias('events', EventDispatcher::class);

        $this->app->singleton(ModuleArtifactPaths::class, fn () => new ModuleArtifactPaths($this->paths(), $this->tenancyState()));
        $this->app->singleton(TenantDiscovery::class, fn () => new TenantDiscovery($this->paths(), $this->tenancyState()));
        $this->app->singleton(VersionRegistry::class, fn () => new VersionRegistry(
            $this->app->make(ConfigRepository::class),
            $this->app->make(ModuleArtifactPaths::class),
        ));
        $this->app->singleton(AssetServer::class, fn () => new AssetServer());
        $this->app->singleton(ValidationExtensionRegistry::class, fn () => new ValidationExtensionRegistry());
        $this->app->singleton(HttpClient::class, fn () => new HttpClient());

        $this->app->singleton('logger', function () {
            $paths = $this->paths();

            return new \Anvyr\Loom\Services\FileLogger(
                logPath: $this->config('logging.path', $paths->storage('logs/loom.log')),
                level: $this->config('logging.level', $this->config('app.log_level', 'info')),
                daily: (bool) $this->config('logging.daily', false),
                maxFiles: (int) $this->config('logging.max_files', 7)
            );
        });
        $this->app->alias('logger', \Psr\Log\LoggerInterface::class);

        $this->app->singleton('exceptions.handler', function () {
            $renderers = (array) $this->config('exceptions.renderers', []);
            $reporters = (array) $this->config('exceptions.reporters', []);
            $logger = $this->app->make(\Psr\Log\LoggerInterface::class);

            return new \Anvyr\Loom\Exceptions\Handler(
                $this->app->get('events'),
                $logger,
                $renderers,
                $reporters
            );
        });
        $this->app->alias('exceptions.handler', \Anvyr\Loom\Exceptions\Handler::class);

        $this->app->singleton('router', function () {
            $router = new Router($this->app->get('events'));
            $router->setApp($this->app);

            $middlewareConfig = (array) $this->config('http.middleware', []);
            $aliases = (array) ($middlewareConfig['aliases'] ?? []);
            $global = (array) ($middlewareConfig['global'] ?? []);

            $aliases = array_merge(
                ['errors' => \Anvyr\Loom\Http\Middleware\ErrorHandlingMiddleware::class],
                $aliases
            );

            foreach ($aliases as $name => $definition) {
                $router->registerMiddleware($name, $definition);
            }

            if ($global === []) {
                $global = ['errors'];
            } else {
                $global = array_values(array_unique(array_merge(['errors'], $global)));
            }

            foreach ($global as $middleware) {
                $router->pushMiddleware($middleware);
            }

            return $router;
        });
        $this->app->alias('router', Router::class);

        $this->app->singleton('db', function () {
            $config = $this->config('db');

            if (!is_array($config)) {
                throw new \RuntimeException('Database configuration not found.');
            }

            if (!isset($config['default'], $config['connections']) || !is_string($config['default']) || !is_array($config['connections'])) {
                throw new \RuntimeException('Database configuration is invalid.');
            }

            $tenancyState = $this->tenancyState();

            if ($tenancyState->isEnabled() && $tenancyState->currentId() !== null) {
                $config = $this->resolveTenantDatabase((string) $tenancyState->currentId(), $config);
            }
            /** @var array{default: string, connections: array<string, array<string, mixed>>} $config */
            return new \Anvyr\Loom\Database\Connection($config);
        });
        $this->app->alias('db', \Anvyr\Loom\Database\Connection::class);

        $this->app->singleton('schema', function () {
            return new Schema($this->app->make(\Anvyr\Loom\Database\Connection::class));
        });
        $this->app->alias('schema', Schema::class);

        \Anvyr\Loom\Database\Model::setConnectionResolver(fn () => $this->app->make(\Anvyr\Loom\Database\Connection::class));

        $this->app->singleton('cache', function () {
            $driver = (string) $this->config('cache.default', 'file');
            $config = $this->config("cache.drivers.{$driver}", []);
            $config = is_array($config) ? $config : [];
            $config['path'] = $config['path'] ?? $this->paths()->storage('cache');
            $prefix = $config['prefix'] ?? $this->config('cache.prefix', 'loom');
            $tenancyState = $this->tenancyState();

            if ($tenancyState->isEnabled() && $tenancyState->currentId() !== null) {
                $prefix = rtrim($prefix, ':') . ':' . $tenancyState->currentId();
            }

            $config['prefix'] = $prefix;

            return match ($driver) {
                'file' => new \Anvyr\Loom\Drivers\Cache\FileCache($config),
                'redis' => new \Anvyr\Loom\Drivers\Cache\RedisCache($config),
                'apcu' => new \Anvyr\Loom\Drivers\Cache\ApcuCache($config),
                default => new \Anvyr\Loom\Drivers\Cache\FileCache($config),
            };
        });
        $this->app->alias('cache', \Anvyr\Loom\Contracts\CacheDriver::class);

        $this->app->bind('tenant', fn () => $this->app->make(TenancyState::class)->current());
        $this->app->alias('tenant', \Anvyr\Loom\Core\Tenancy\TenantContext::class);

        $this->app->singleton('cache.tags', function () {
            return new \Anvyr\Loom\Support\Cache\CacheTagManager(
                $this->app->make(\Anvyr\Loom\Contracts\CacheDriver::class)
            );
        });
        $this->app->alias('cache.tags', \Anvyr\Loom\Support\Cache\CacheTagManager::class);

        $this->app->singleton(\Anvyr\Loom\Contracts\ParserInterface::class, function () {
            $driver = (string) $this->config('content.parser.driver', 'commonmark');
            $driverConfig = $this->config("content.parser.drivers.{$driver}", []);
            $driverConfig = is_array($driverConfig) ? $driverConfig : [];

            return (new \Anvyr\Loom\Services\Parsers\ParserFactory())->make($driver, $driverConfig);
        });

        $this->app->singleton('parser', function () {
            return new \Anvyr\Loom\Services\ContentParser(
                $this->app->make(\Anvyr\Loom\Contracts\CacheDriver::class),
                $this->app->make(\Anvyr\Loom\Contracts\ParserInterface::class),
                $this->configRepository()
            );
        });
        $this->app->alias('parser', \Anvyr\Loom\Services\ContentParser::class);

        $this->app->singleton('view', fn () => new \Anvyr\Loom\Services\ViewEngine(
            $this->resolveViewPath(),
            $this->resolveCompiledViewPath()
        ));
        $this->app->alias('view', \Anvyr\Loom\Services\ViewEngine::class);

        $this->app->singleton(\Anvyr\Loom\Database\Migrations\MigrationRepository::class, function () {
            return new \Anvyr\Loom\Database\Migrations\MigrationRepository(
                $this->app->make(\Anvyr\Loom\Database\Connection::class),
                $this->app->make(Schema::class)
            );
        });

        $this->app->singleton('migrator', function () {
            return new \Anvyr\Loom\Database\Migrations\Migrator(
                $this->app->make(\Anvyr\Loom\Database\Connection::class),
                $this->app->make(Schema::class),
                $this->app->make(\Anvyr\Loom\Database\Migrations\MigrationRepository::class)
            );
        });
        $this->app->alias('migrator', \Anvyr\Loom\Database\Migrations\Migrator::class);

        $this->app->singleton('session', fn () => new \Anvyr\Loom\Services\SessionManager());
        $this->app->alias('session', \Anvyr\Loom\Services\SessionManager::class);

        $this->app->singleton('rate_limiter', function () {
            $rateLimiter = new \Anvyr\Loom\Http\RateLimiting\RateLimiter(
                $this->app->make(\Anvyr\Loom\Contracts\CacheDriver::class)
            );
            $rateLimiter->whitelist((array) $this->config('http.rate_limit.whitelist', []));

            return $rateLimiter;
        });
        $this->app->alias('rate_limiter', \Anvyr\Loom\Http\RateLimiting\RateLimiter::class);
        $this->app->alias('rate_limiter', \Anvyr\Loom\Contracts\RateLimiterInterface::class);

        $this->app->singleton('schedule', fn () => new \Anvyr\Loom\Scheduling\Schedule());
        $this->app->alias('schedule', \Anvyr\Loom\Scheduling\Schedule::class);

        $this->app->singleton('queue', function () {
            $driver = (string) $this->config('queue.driver', 'database');

            $queueDriver = match ($driver) {
                'sync' => new \Anvyr\Loom\Drivers\Queue\SyncQueueDriver(),
                'database' => new \Anvyr\Loom\Drivers\Queue\DatabaseQueueDriver($this->app->get('db')),
                default => throw new \RuntimeException("Unsupported queue driver: {$driver}"),
            };

            return new \Anvyr\Loom\Queue\QueueManager($queueDriver);
        });
        $this->app->alias('queue', \Anvyr\Loom\Queue\QueueManager::class);

        $this->app->singleton('queue.worker', function () {
            return new \Anvyr\Loom\Queue\Worker(
                $this->app->get('queue'),
                $this->app->get('events'),
            );
        });
        $this->app->alias('queue.worker', \Anvyr\Loom\Queue\Worker::class);

        $this->app->singleton('storage', function () {
            $config = $this->config('filesystems', []);
            if (!is_array($config) || !isset($config['disks']) || !is_array($config['disks'])) {
                throw new \RuntimeException('Filesystem configuration is invalid.');
            }

            /** @var array{default?: string, disks: array<string, array<string, mixed>>} $config */
            return new \Anvyr\Loom\Services\StorageManager($config);
        });
        $this->app->alias('storage', \Anvyr\Loom\Services\StorageManager::class);

        $this->app->singleton('modules', function () {
            $manager = new ModuleManager($this->app);
            $manager->load();
            $manager->register();
            return $manager;
        });
        $this->app->alias('modules', ModuleManager::class);

        $this->app->events->dispatch('migrations.registering', $this->app);

        $this->app->events->listen('commands.registering', function ($registry) {
            $registry->register('schedule:run', \Anvyr\Loom\Commands\ScheduleRunCommand::class);
            $registry->register('queue:work', \Anvyr\Loom\Commands\Queue\WorkCommand::class);
        });
    }

    public function boot(): void
    {
        if ($this->app->has('modules')) {
            $this->app->make('modules')->boot();
        }

        if ((bool) $this->config('app.cron_enabled', false)) {
            $router = $this->app->make('router');
            $router->get('/system/cron', [\Anvyr\Loom\Http\Controllers\WebCronController::class, 'handle']);
        }
    }

    /**
     * @param array<string, mixed> $config
     * @return array<string, mixed>
     */
    private function resolveTenantDatabase(string $tenantId, array $config): array
    {
        $tenancyState = $this->tenancyState();
        $tenancyConfig = $tenancyState->config();
        $dbConfig = $tenancyConfig['database'] ?? [];

        if (empty($dbConfig['enabled'])) {
            return $config;
        }

        $map = $dbConfig['map'] ?? [];
        if (isset($map[$tenantId])) {
            $mapped = $map[$tenantId];

            if (is_string($mapped)) {
                if (isset($config['connections'][$mapped])) {
                    $config['default'] = $mapped;
                    return $config;
                }
                throw new \RuntimeException("Tenant '{$tenantId}' mapped to unknown connection '{$mapped}'.");
            }

            if (is_array($mapped)) {
                $defaultConnection = $config['default'];
                $config['connections'][$defaultConnection] = array_merge(
                    $config['connections'][$defaultConnection] ?? [],
                    $mapped
                );
                return $config;
            }
        }

        $pattern = $dbConfig['pattern'] ?? 'loom_{tenant}';
        $dbName = str_replace('{tenant}', $tenantId, $pattern);

        $defaultConnection = $config['default'];
        if (isset($config['connections'][$defaultConnection])) {
            $config['connections'][$defaultConnection]['database'] = $dbName;
        }

        return $config;
    }

    private function configRepository(): ConfigRepository
    {
        return $this->app->make(ConfigRepository::class);
    }

    private function paths(): Paths
    {
        return $this->app->make(Paths::class);
    }

    private function tenancyState(): TenancyState
    {
        return $this->app->make(TenancyState::class);
    }

    private function config(string $key, mixed $default = null): mixed
    {
        return $this->configRepository()->get($key, $default);
    }

    private function resolveViewPath(): string
    {
        $configuredPath = $this->config('view.path', 'user/views');
        if (!is_string($configuredPath) || trim($configuredPath) === '') {
            $configuredPath = 'user/views';
        }

        if (Paths::isAbsolute($configuredPath)) {
            return rtrim($configuredPath, '/\\');
        }

        $normalizedPath = trim($configuredPath, '/\\');
        $paths = $this->paths();
        $tenancyState = $this->tenancyState();

        if ($tenancyState->isEnabled() && $tenancyState->currentId() !== null) {
            if ($normalizedPath === 'user') {
                return $paths->tenantUser();
            }

            if (str_starts_with($normalizedPath, 'user/')) {
                return $paths->tenantUser(substr($normalizedPath, strlen('user/')));
            }
        }

        return $paths->base($normalizedPath);
    }

    private function resolveCompiledViewPath(): string
    {
        $configuredPath = $this->config('view.compiled', 'cache/views');
        if (!is_string($configuredPath) || trim($configuredPath) === '') {
            $configuredPath = 'cache/views';
        }

        return $this->paths()->storage($configuredPath);
    }
}
