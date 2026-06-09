<?php

declare(strict_types=1);

namespace Anvyr\Loom\Core;

use Anvyr\Loom\Commands\CommandRegistry;
use Anvyr\Loom\Contracts\Module;
use Anvyr\Loom\Core\Tenancy\ModuleArtifactPaths;
use Anvyr\Loom\Core\Tenancy\TenancyState;
use Anvyr\Loom\Exceptions\ModuleException;
use Anvyr\Loom\Http\AssetServer;
use Anvyr\Loom\Http\Routing\RouteFileLoader;
use Anvyr\Loom\Http\Routing\Router;

/** @phpstan-type ManifestArray array<string, mixed> */
class ModuleManager
{
    private Application $app;
    private ModuleArtifactPaths $artifactPaths;
    private VersionRegistry $versionRegistry;
    private ConfigRepository $configRepository;
    private TenancyState $tenancyState;
    private AssetServer $assetServer;
    private string $basePath;

    /** @var array<string, Module> */
    private array $modules = [];

    /** @var array<string, ModuleManifest> */
    private array $manifests = [];

    private bool $booted = false;

    public function __construct(Application $app)
    {
        $this->app = $app;
        $this->artifactPaths = $app->make(ModuleArtifactPaths::class);
        $this->versionRegistry = $app->make(VersionRegistry::class);
        $this->configRepository = $app->make(ConfigRepository::class);
        $this->tenancyState = $app->make(TenancyState::class);
        $this->assetServer = $app->make(AssetServer::class);
        $this->basePath = $app->basePath();
    }

    public function load(): self
    {
        $compiledPath = null;
        foreach ($this->artifactPaths->compiledCandidates($this->basePath) as $candidate) {
            if (file_exists($candidate)) {
                $compiledPath = $candidate;
                break;
            }
        }

        if ($compiledPath === null) {
            return $this;
        }

        $compiledContents = file_get_contents($compiledPath);
        $compiled = is_string($compiledContents) ? json_decode($compiledContents, true) : null;

        if (!is_array($compiled) || !isset($compiled['modules'])) {
            return $this;
        }

        $this->registerAutoloader();

        foreach ($compiled['modules'] as $moduleData) {
            if (!is_array($moduleData)) {
                continue;
            }

            if (!($moduleData['enabled'] ?? false)) {
                continue;
            }

            $name = (string) ($moduleData['name'] ?? '');
            if ($name === '') {
                continue;
            }

            $manifest = ModuleManifest::fromArray($name, $moduleData, true);
            $this->loadModule($manifest);
        }

        return $this;
    }

    private function loadModule(ModuleManifest $manifest): void
    {
        $name = $manifest->name;
        $entryClass = $manifest->entry;
        $path = $this->resolveManifestPath($manifest);

        if (!class_exists($entryClass)) {
            throw new ModuleException("Module '{$name}' entry class not found: {$entryClass}");
        }

        $module = new $entryClass($path, $manifest);

        if (!$module instanceof Module) {
            throw new ModuleException("Module '{$name}' must implement " . Module::class);
        }

        $this->registerModuleAssets($module);

        $configDir = $path . '/config';
        if (is_dir($configDir)) {
            $this->configRepository->registerNamespace($name, $configDir);
        }

        $this->modules[$name] = $module;
        $this->manifests[$name] = $manifest;
    }

    private function registerModuleAssets(Module $module): void
    {
        if (!method_exists($module, 'publicPath') || !method_exists($module, 'assetsPrefix')) {
            return;
        }

        $publicPath = $module->publicPath();
        $prefix = $module->assetsPrefix();

        if ($publicPath && $prefix) {
            $this->assetServer->registerModule($prefix, $publicPath);
        }
    }

    public function registerAutoloader(): void
    {
        $autoloadPath = null;
        foreach ($this->artifactPaths->autoloadCandidates($this->basePath) as $candidate) {
            if (file_exists($candidate)) {
                $autoloadPath = $candidate;
                break;
            }
        }

        if ($autoloadPath === null) {
            return;
        }

        $mappings = require $autoloadPath;

        if (!isset($mappings['psr-4'])) {
            return;
        }

        $psr4 = $mappings['psr-4'];
        $files = $mappings['files'] ?? [];

        foreach ($files as $file) {
            if (file_exists($file)) {
                require_once $file;
            }
        }

        spl_autoload_register(function ($class) use ($psr4) {
            foreach ($psr4 as $namespace => $path) {
                if (!str_starts_with($class, $namespace)) {
                    continue;
                }

                $relativeClass = substr($class, strlen($namespace));
                $file = $path . '/' . str_replace('\\', '/', $relativeClass) . '.php';

                if (file_exists($file)) {
                    require_once $file;
                    return;
                }
            }
        });
    }

    public function register(): self
    {
        foreach ($this->modules as $module) {
            $module->register($this->app);
        }

        if ($this->app->has('events')) {
            $this->app->get('events')->listen('commands.registering', function (object $registry): void {
                if ($registry instanceof CommandRegistry) {
                    $this->registerManifestCommands($registry);
                }
            });
        }

        return $this;
    }

    public function boot(): self
    {
        if ($this->booted) {
            return $this;
        }

        foreach ($this->modules as $name => $module) {
            $this->autoRegisterViews($name);
            $module->boot($this->app);
        }

        $this->booted = true;

        return $this;
    }

    public function loadRoutes(): void
    {
        foreach (array_keys($this->modules) as $name) {
            $this->autoLoadRoutes($name);
        }
    }

    private function resolveManifestPath(ModuleManifest $manifest): string
    {
        return str_starts_with($manifest->path, '/')
            ? $manifest->path
            : $this->basePath . '/' . $manifest->path;
    }

    private function autoRegisterViews(string $name): void
    {
        if (!$this->app->has('view')) {
            return;
        }

        $manifest = $this->manifests[$name];

        if ($manifest->views === null) {
            return;
        }

        $viewsDir = $this->resolveManifestPath($manifest) . '/' . ltrim($manifest->views, '/');
        if (is_dir($viewsDir)) {
            $this->app->get('view')->namespace($name, $viewsDir);
        }
    }

    private function autoLoadRoutes(string $name): void
    {
        $manifest = $this->manifests[$name];

        if ($manifest->routes === []) {
            return;
        }

        $router = $this->app->has('router') ? $this->app->get('router') : null;
        if ($router === null) {
            return;
        }

        $basePath = $this->resolveManifestPath($manifest);
        $app = $this->app;

        foreach ($manifest->routes as $type => $relativePath) {
            $routePath = $basePath . '/' . ltrim($relativePath, '/');
            if (!file_exists($routePath)) {
                continue;
            }

            if ($type === 'api') {
                $router->group(['prefix' => 'api'], static function (Router $router) use ($app, $routePath): void {
                    RouteFileLoader::register($routePath, $router, $app);
                });
            } else {
                RouteFileLoader::register($routePath, $router, $app);
            }
        }
    }

    private function registerManifestCommands(CommandRegistry $registry): void
    {
        foreach ($this->manifests as $manifest) {
            foreach ($manifest->commands as $signature => $class) {
                $registry->register($signature, $class);
            }
        }
    }

    /** @return array<string, Module> */
    public function all(): array
    {
        return $this->modules;
    }

    public function get(string $name): ?Module
    {
        return $this->modules[$name] ?? null;
    }

    public function has(string $name): bool
    {
        return isset($this->modules[$name]);
    }

    /** @return array<string, ManifestArray> */
    public function discover(): array
    {
        return array_merge(
            $this->discoverFromConfig(),
            $this->discoverFromFilesystem(),
            $this->discoverFromComposer(),
        );
    }

    /** @return array<string, ManifestArray> */
    private function discoverFromConfig(): array
    {
        $modules = [];
        $config = $this->modulesConfig();
        $configuredModules = $config['modules'] ?? [];

        if (!is_array($configuredModules)) {
            return $modules;
        }

        foreach ($configuredModules as $name => $moduleConfig) {
            if (is_string($moduleConfig)) {
                $moduleConfig = ['path' => $moduleConfig];
            }

            $manifestPath = $this->resolveModulePath($moduleConfig['path'] ?? $name) . '/module.json';

            if (file_exists($manifestPath)) {
                $manifestContents = file_get_contents($manifestPath);
                $manifest = is_string($manifestContents) ? json_decode($manifestContents, true) : null;
                if (is_array($manifest)) {
                    $modules[$name] = array_merge($manifest, [
                        'path' => $moduleConfig['path'] ?? $name,
                        'source' => 'config',
                    ]);
                }
            }
        }

        return $modules;
    }

    /** @return array<string, ManifestArray> */
    private function discoverFromFilesystem(): array
    {
        $modules = [];
        $config = $this->modulesConfig();
        $paths = $config['paths'] ?? [];
        $tenantPaths = $this->getTenantModulePaths();
        if (!empty($tenantPaths)) {
            $paths = array_merge($paths, $tenantPaths);
        }
        $paths = array_values(array_unique(array_filter($paths, 'is_string')));

        foreach ($paths as $path) {
            $resolvedPath = $this->resolveModulePath($path);

            if (str_contains($path, '*')) {
                $matchedPaths = glob($resolvedPath) ?: [];
                foreach ($matchedPaths as $matchedPath) {
                    if (is_dir($matchedPath)) {
                        $matchedPath = realpath($matchedPath);
                        if ($matchedPath === false) {
                            continue;
                        }
                        $module = $this->loadManifestFromPath($matchedPath);
                        if ($module) {
                            $modules[$module['name']] = array_merge($module, [
                                'path' => $matchedPath,
                                'source' => 'filesystem',
                            ]);
                        }
                    }
                }
            } else {
                if (is_dir($resolvedPath)) {
                    $module = $this->loadManifestFromPath($resolvedPath);
                    if ($module) {
                        $modules[$module['name']] = array_merge($module, [
                            'path' => $resolvedPath,
                            'source' => 'filesystem',
                        ]);
                    }
                }
            }
        }

        return $modules;
    }

    /** @return string[] */
    private function getTenantModulePaths(): array
    {
        if (!$this->tenancyState->isEnabled()) {
            return [];
        }

        $tenantId = $this->tenancyState->currentId();
        if (!is_string($tenantId) || $tenantId === '') {
            return [];
        }

        $config = $this->modulesConfig();
        $paths = $config['tenant_paths'] ?? [];
        if (is_string($paths)) {
            $paths = [$paths];
        }

        $resolved = [];
        foreach ($paths as $path) {
            if (!is_string($path) || $path === '') {
                continue;
            }
            $resolved[] = str_replace('{tenant}', $tenantId, $path);
        }

        return $resolved;
    }

    /** @return array<string, mixed> */
    private function modulesConfig(): array
    {
        $config = $this->configRepository->get('modules', []);

        return is_array($config) ? $config : [];
    }

    /** @return array<string, ManifestArray> */
    private function discoverFromComposer(): array
    {
        $modules = [];
        $installedPath = $this->basePath . '/vendor/composer/installed.json';

        if (!file_exists($installedPath)) {
            return $modules;
        }

        $installedContents = file_get_contents($installedPath);
        $installed = is_string($installedContents) ? json_decode($installedContents, true) : null;
        $packages = $installed['packages'] ?? [];

        foreach ($packages as $package) {
            if (($package['type'] ?? '') === 'loom-module') {
                $packagePath = $this->basePath . '/vendor/' . $package['name'];
                $module = $this->loadManifestFromPath($packagePath);

                if ($module) {
                    $modules[$module['name']] = array_merge($module, [
                        'path' => $packagePath,
                        'source' => 'composer',
                        'package' => $package['name'],
                    ]);
                }
            }
        }

        return $modules;
    }

    /** @return ManifestArray|null */
    private function loadManifestFromPath(string $path): ?array
    {
        $manifestPath = $path . '/module.json';

        if (!file_exists($manifestPath)) {
            return null;
        }

        $manifestContents = file_get_contents($manifestPath);
        $manifest = is_string($manifestContents) ? json_decode($manifestContents, true) : null;

        if (!is_array($manifest) || !isset($manifest['name'])) {
            return null;
        }

        return $manifest;
    }

    private function resolveModulePath(string $path): string
    {
        if (str_starts_with($path, '/')) {
            return $path;
        }

        return $this->basePath . '/' . $path;
    }

    /**
     * @param ManifestArray $manifest
     * @param array<string, ManifestArray> $availableManifests
     * @return list<string>
     */
    public function validate(string $name, array $manifest, array $availableManifests = []): array
    {
        $issues = [];

        $entryClass = $manifest['entry'] ?? null;
        if (!$entryClass) {
            $issues[] = "Module '{$name}' missing 'entry' in manifest";
        }

        if ($availableManifests === []) {
            $loaded = [];
            foreach ($this->manifests as $loadedName => $loadedManifest) {
                $loaded[$loadedName] = $loadedManifest->toArray();
            }
            $availableManifests = array_merge($loaded, $this->discover());
        }

        // Check version requirements
        $requires = $manifest['requires'] ?? [];

        foreach ($requires as $dependency => $constraint) {
            if ($dependency === 'core') {
                if (!$this->versionRegistry->satisfies($this->versionRegistry->getVersion('core'), $constraint)) {
                    $issues[] = "Module '{$name}' requires core {$constraint}, but current is " . $this->versionRegistry->getVersion('core');
                }
            } elseif ($dependency === 'php') {
                if (!$this->versionRegistry->satisfies(PHP_VERSION, $constraint)) {
                    $issues[] = "Module '{$name}' requires PHP {$constraint}, but current is " . PHP_VERSION;
                }
            } else {
                // Check other module dependencies
                $dependencyManifest = $availableManifests[$dependency] ?? null;

                if ($dependencyManifest === null) {
                    $issues[] = "Module '{$name}' requires '{$dependency}' but it was not found";
                    continue;
                }

                $dependencyVersion = $dependencyManifest['version'] ?? '0.0.0';

                if (!$this->versionRegistry->satisfies($dependencyVersion, (string) $constraint)) {
                    $issues[] = "Module '{$name}' requires '{$dependency}' version {$constraint}, but found {$dependencyVersion}";
                }
            }
        }

        // Check for conflicts
        $conflicts = $manifest['conflicts'] ?? [];
        foreach ($conflicts as $conflictingModule) {
            if ($this->has($conflictingModule)) {
                $issues[] = "Module '{$name}' conflicts with '{$conflictingModule}'";
            }
        }

        return $issues;
    }

    /**
     * @param array<string, ManifestArray> $modules
     * @return list<string>
     */
    public function resolveLoadOrder(array $modules): array
    {
        $sorted = [];
        $visited = [];
        $visiting = [];

        $visit = function (string $name) use (&$visit, &$sorted, &$visited, &$visiting, $modules) {
            if (isset($visited[$name])) {
                return;
            }

            if (isset($visiting[$name])) {
                throw new ModuleException("Circular dependency detected involving module '{$name}'");
            }

            $visiting[$name] = true;

            $manifest = $modules[$name] ?? [];
            $requires = $manifest['requires'] ?? [];

            foreach ($requires as $dependency => $constraint) {
                if ($dependency !== 'core' && $dependency !== 'php' && isset($modules[$dependency])) {
                    $visit($dependency);
                }
            }

            unset($visiting[$name]);
            $visited[$name] = true;
            $sorted[] = $name;
        };

        foreach (array_keys($modules) as $name) {
            $visit($name);
        }

        return $sorted;
    }
}
