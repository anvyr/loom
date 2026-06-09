<?php

declare(strict_types=1);

namespace Anvyr\Loom\Commands\Module;

use Anvyr\Loom\Commands\Command;
use Anvyr\Loom\Commands\Concerns\InteractsWithTenancy;
use Anvyr\Loom\Core\Application;
use Anvyr\Loom\Core\ModuleManager;
use Anvyr\Loom\Core\ModuleManifest;
use Anvyr\Loom\Core\Tenancy\ModuleArtifactPaths;
use Anvyr\Loom\Core\VersionRegistry;

class CompileModuleCommand extends Command
{
    use InteractsWithTenancy;

    public static function category(): string
    {
        return 'Modules';
    }

    public function __construct(
        private readonly Application $app
    ) {
    }

    public function signature(): string
    {
        return 'module:compile [--tenant=] [--all-tenants]';
    }

    public function description(): string
    {
        return 'Compile module manifest (validate, resolve load order and dependencies)';
    }

    public function handle(): int
    {
        $artifactPaths = $this->app->make(ModuleArtifactPaths::class);

        if ((bool) $this->option('all-tenants', false)) {
            return $this->handleAllTenants();
        }

        $this->line('Compiling modules...');
        $this->line();

        $moduleManager = $this->app->make(ModuleManager::class);
        $versionRegistry = $this->app->make(VersionRegistry::class);

        $discovered = $moduleManager->discover();

        if (empty($discovered)) {
            $this->line('No modules discovered.');
            return 0;
        }

        $this->line("Discovered \033[32m" . count($discovered) . "\033[0m modules");
        $this->line();

        $statePath = $this->resolveStatePathForRead($this->app->basePath());
        $state = [];

        if (file_exists($statePath)) {
            $contents = file_get_contents($statePath);
            $state = is_string($contents) ? json_decode($contents, true) ?? [] : [];
        }

        $enabledModules = $state['enabled'] ?? [];

        $validated = [];
        $errors = [];

        foreach ($discovered as $name => $manifest) {
            $enabled = in_array($name, $enabledModules, true);

            if (!$enabled) {
                $this->line("  \033[90m[SKIP]\033[0m {$name} (disabled)");
                continue;
            }

            $issues = $moduleManager->validate($name, $manifest, $discovered);

            if (!empty($issues)) {
                $errors[$name] = $issues;
                $this->line("  \033[31m[FAIL]\033[0m {$name}");
                foreach ($issues as $issue) {
                    $this->line("    ⚠️  {$issue}");
                }
            } else {
                $validated[$name] = $manifest;
                $this->line("  \033[32m[OK]\033[0m {$name} @{$manifest['version']}");
            }
        }

        $this->line();

        if (!empty($errors)) {
            $this->line("\033[31m✗ Compilation failed with " . count($errors) . " error(s)\033[0m");
            return 1;
        }

        try {
            $loadOrder = $moduleManager->resolveLoadOrder($validated);
            $this->line('Resolved load order: ' . implode(' → ', $loadOrder));
            $this->line();
        } catch (\Exception $e) {
            $this->line("\033[31m✗ Failed to resolve load order: {$e->getMessage()}\033[0m");
            return 1;
        }

        $compiled = [
            'timestamp' => date('c'),
            'version' => $versionRegistry->getVersion('core'),
            'modules' => [],
        ];

        foreach ($loadOrder as $index => $name) {
            $manifest = $validated[$name];

            $typed = ModuleManifest::fromArray($name, $manifest, true);

            $compiled['modules'][] = array_merge(
                $typed->toArray(),
                ['load_order' => $index + 1]
            );
        }

        $compiledPath = $artifactPaths->compiledPath(basePath: $this->app->basePath());
        $compiledDir = dirname($compiledPath);
        if (!is_dir($compiledDir)) {
            mkdir($compiledDir, 0755, true);
        }
        file_put_contents($compiledPath, json_encode($compiled, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        /** @var list<array{name?: string, path: string, entry?: string|null}> $compiledModules */
        $compiledModules = $compiled['modules'];
        $this->generateAutoloader($compiledModules);

        try {
            $this->verifyEntryAutoload($compiledModules);
        } catch (\RuntimeException $e) {
            $this->line("\033[31m✗ Autoload verification failed: {$e->getMessage()}\033[0m");
            return 1;
        }

        $this->line("\033[32m✓ Successfully compiled " . count($compiled['modules']) . " module(s)\033[0m");
        $this->line('  Written to: ' . $this->relativeToBase($compiledPath));
        $this->line('  Autoloader: ' . $this->relativeToBase($artifactPaths->autoloadPath(basePath: $this->app->basePath())));

        return 0;
    }

    private function handleAllTenants(): int
    {
        try {
            $tenants = $this->resolveTenantSelection(allowAllTenants: true, fallbackToCurrentTenant: false);
        } catch (\RuntimeException $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }

        if ($tenants === []) {
            $this->warning('No tenants discovered under user tenancy root.');
            return self::SUCCESS;
        }

        $this->info('Compiling modules for all tenants...');
        $failures = [];

        foreach ($tenants as $tenantId) {
            $this->line();
            $this->line("\033[1m[tenant: {$tenantId}]\033[0m");

            $exitCode = $this->runLoomSubcommand($this->app->basePath(), 'module:compile', $tenantId);

            if ($exitCode !== 0) {
                $failures[] = $tenantId;
            }
        }

        if ($failures !== []) {
            $this->error('Failed tenants: ' . implode(', ', $failures));
            return self::FAILURE;
        }

        $this->success('Compiled modules for all tenants.');
        return self::SUCCESS;
    }

    /** @param list<array{name?: string, path: string, entry?: string|null}> $modules */
    private function generateAutoloader(array $modules): void
    {
        $artifactPaths = $this->app->make(ModuleArtifactPaths::class);
        $autoloadMappings = [];
        $files = [];

        foreach ($modules as $moduleData) {
            $vendorAutoload = $moduleData['path'] . '/vendor/autoload.php';
            if (file_exists($vendorAutoload)) {
                $files[] = $vendorAutoload;
            }

            $composerPath = $moduleData['path'] . '/composer.json';

            if (!file_exists($composerPath)) {
                continue;
            }

            $composerContents = file_get_contents($composerPath);
            $composer = is_string($composerContents) ? json_decode($composerContents, true) : null;

            if (!isset($composer['autoload']['psr-4'])) {
                continue;
            }

            foreach ($composer['autoload']['psr-4'] as $namespace => $path) {
                $fullPath = $moduleData['path'] . '/' . rtrim($path, '/');
                $autoloadMappings[$namespace] = $fullPath;
            }
        }

        $config = [
            'psr-4' => $autoloadMappings,
            'files' => $files,
        ];

        $php = "<?php\n\n";
        $php .= "/**\n";
        $php .= " * Auto-generated module autoloader\n";
        $php .= ' * Generated: ' . date('Y-m-d H:i:s') . "\n";
        $php .= " * Do not edit manually - run 'php loom module:compile' to regenerate\n";
        $php .= " */\n\n";
        $php .= 'return ' . var_export($config, true) . ";\n";

        $autoloadPath = $artifactPaths->autoloadPath(basePath: $this->app->basePath());
        $autoloadDir = dirname($autoloadPath);
        if (!is_dir($autoloadDir)) {
            mkdir($autoloadDir, 0755, true);
        }
        file_put_contents($autoloadPath, $php);

        clearstatcache(true, $autoloadPath);

        if (function_exists('opcache_invalidate')) {
            @opcache_invalidate($autoloadPath, true);
        }
    }

    /** @param list<array{name?: string, path: string, entry?: string|null}> $modules */
    private function verifyEntryAutoload(array $modules): void
    {
        $autoloadPath = $this->app->make(ModuleArtifactPaths::class)->autoloadPath(basePath: $this->app->basePath());

        if (!file_exists($autoloadPath)) {
            throw new \RuntimeException('module autoloader not generated');
        }

        clearstatcache(true, $autoloadPath);

        if (function_exists('opcache_invalidate')) {
            @opcache_invalidate($autoloadPath, true);
        }

        $config = require $autoloadPath;
        $mappings = $config['psr-4'] ?? $config;

        if (!is_array($mappings)) {
            throw new \RuntimeException('invalid module autoloader structure');
        }

        foreach ($mappings as $namespace => $path) {
            spl_autoload_register(static function ($class) use ($namespace, $path): void {
                if (!str_starts_with($class, $namespace)) {
                    return;
                }

                $relativeClass = substr($class, strlen($namespace));
                $file = $path . '/' . str_replace('\\', '/', $relativeClass) . '.php';

                if (file_exists($file)) {
                    require_once $file;
                }
            });
        }

        $missing = [];

        foreach ($modules as $moduleData) {
            $entry = $moduleData['entry'] ?? null;

            if ($entry === null || $entry === '') {
                continue;
            }

            if (!class_exists($entry)) {
                $name = $moduleData['name'] ?? 'unknown';
                $missing[] = sprintf('%s (%s)', $name, $entry);
            }
        }

        if ($missing !== []) {
            throw new \RuntimeException('unable to autoload entry class for: ' . implode(', ', $missing));
        }
    }

    private function relativeToBase(string $path): string
    {
        $base = $this->app->basePath();
        if (str_starts_with($path, $base . '/')) {
            return substr($path, strlen($base) + 1);
        }

        return $path;
    }

}
