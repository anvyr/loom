<?php

declare(strict_types=1);

namespace Anvyr\Loom\Commands\Module;

use Anvyr\Loom\Commands\Command;
use Anvyr\Loom\Commands\Concerns\InteractsWithTenancy;
use Anvyr\Loom\Core\Application;
use Anvyr\Loom\Core\ModuleManager;
use Anvyr\Loom\Core\Tenancy\ModuleArtifactPaths;

class ListModuleCommand extends Command
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
        return 'module:list [--tenant=] [--all-tenants]';
    }

    public function description(): string
    {
        return 'List all discovered modules';
    }

    public function handle(): int
    {
        $artifactPaths = $this->app->make(ModuleArtifactPaths::class);

        if ((bool) $this->option('all-tenants', false)) {
            return $this->handleAllTenants();
        }

        $moduleManager = $this->app->make(ModuleManager::class);
        $discovered = $moduleManager->discover();

        if (empty($discovered)) {
            $this->line('No modules discovered.');
            return 0;
        }

        $statePath = $this->firstExisting($artifactPaths->stateCandidates($this->app->basePath()))
            ?? $artifactPaths->statePath(basePath: $this->app->basePath());
        $enabledModules = [];
        if (file_exists($statePath)) {
            $contents = file_get_contents($statePath);
            $state = is_string($contents) ? json_decode($contents, true) : null;
            $enabledModules = $state['enabled'] ?? [];
        }

        $compiledPath = $this->firstExisting($artifactPaths->compiledCandidates($this->app->basePath()))
            ?? $artifactPaths->compiledPath(basePath: $this->app->basePath());
        $compiledModules = [];
        if (file_exists($compiledPath)) {
            $contents = file_get_contents($compiledPath);
            $compiledData = is_string($contents) ? json_decode($contents, true) : null;
            foreach ($compiledData['modules'] ?? [] as $m) {
                $compiledModules[$m['name']] = true;
            }
        }

        $this->line("\033[1mModules\033[0m");
        $this->line(sprintf('  %-20s %-15s %-15s %s', 'Name', 'Version', 'Status', 'Path'));
        $this->line('  ' . str_repeat('-', 80));

        foreach ($discovered as $name => $manifest) {
            $version = $manifest['version'] ?? 'unknown';
            $isEnabled = in_array($name, $enabledModules, true);
            $isCompiled = isset($compiledModules[$name]);

            if ($isEnabled) {
                if ($isCompiled) {
                    $status = "\033[32mEnabled\033[0m";
                } else {
                    $status = "\033[33mEnabled (Pending)\033[0m";
                }
            } else {
                $status = "\033[90mDisabled\033[0m";
            }

            $path = $manifest['path'];
            if (str_starts_with($path, $this->app->basePath())) {
                $path = '.' . substr($path, strlen($this->app->basePath()));
            }

            $this->line(sprintf(
                '  %-20s %-15s %-25s %s',
                $name,
                $version,
                $status,
                "\033[90m{$path}\033[0m"
            ));
        }

        $this->line();
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

        foreach ($tenants as $tenantId) {
            $this->line();
            $this->line("\033[1m[tenant: {$tenantId}]\033[0m");

            $exitCode = $this->runLoomSubcommand($this->app->basePath(), 'module:list', $tenantId);
            if ($exitCode !== 0) {
                return self::FAILURE;
            }
        }

        return self::SUCCESS;
    }

    /**
     * @param array<int, string> $paths
     */
    private function firstExisting(array $paths): ?string
    {
        foreach ($paths as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }

        return null;
    }
}
