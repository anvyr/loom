<?php

declare(strict_types=1);

namespace Anvyr\Loom\Commands\Module;

use Anvyr\Loom\Commands\Command;
use Anvyr\Loom\Commands\Concerns\InteractsWithTenancy;
use Anvyr\Loom\Core\Application;
use Anvyr\Loom\Core\ModuleManager;
use Anvyr\Loom\Core\Tenancy\ModuleArtifactPaths;

class EnableModuleCommand extends Command
{
    use InteractsWithTenancy;

    public static function category(): string
    {
        return 'Modules';
    }

    public function __construct(
        private readonly Application $app,
        private readonly ModuleManager $moduleManager
    ) {
    }

    public function signature(): string
    {
        return 'module:enable {module} [--tenant=] [--all-tenants]';
    }

    public function description(): string
    {
        return 'Enable a module';
    }

    public function handle(): int
    {
        $moduleName = $this->argument(0);
        $artifactPaths = $this->app->make(ModuleArtifactPaths::class);

        if (!$moduleName) {
            $this->error('Module name is required');
            $this->line('Usage: loom module:enable <module>');
            return 1;
        }

        $discovered = $this->moduleManager->discover();

        if (!isset($discovered[$moduleName])) {
            $this->error("Module '{$moduleName}' not found");
            $this->line();
            $this->line('Available modules:');
            foreach (array_keys($discovered) as $availableModule) {
                $this->line("  - {$availableModule}");
            }
            return 1;
        }

        if ((bool) $this->option('all-tenants', false)) {
            return $this->handleAllTenants($moduleName);
        }

        $statePath = $artifactPaths->statePath(basePath: $this->app->basePath());
        $readPath = $this->resolveStatePathForRead($this->app->basePath());
        $stateDir = dirname($statePath);
        if (!is_dir($stateDir)) {
            mkdir($stateDir, 0755, true);
        }

        $state = [];
        if (file_exists($readPath)) {
            $contents = file_get_contents($readPath);
            $state = is_string($contents) ? json_decode($contents, true) ?? [] : [];
        }

        $enabled = $state['enabled'] ?? [];

        if (!in_array($moduleName, $enabled, true)) {
            $enabled[] = $moduleName;
            $state['enabled'] = $enabled;

            file_put_contents($statePath, json_encode($state, JSON_PRETTY_PRINT));

            $this->line("\033[32m✓\033[0m Enabled module: {$moduleName}");
            $this->line('');

            $compiler = new CompileModuleCommand($this->app);
            return $compiler->handle();
        } else {
            $this->line("Module '{$moduleName}' is already enabled");
        }

        return 0;
    }

    private function handleAllTenants(string $moduleName): int
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

        $this->info("Enabling module '{$moduleName}' for all tenants...");
        $failures = [];

        foreach ($tenants as $tenantId) {
            $this->line();
            $this->line("\033[1m[tenant: {$tenantId}]\033[0m");

            $exitCode = $this->runLoomSubcommand($this->app->basePath(), 'module:enable ' . escapeshellarg($moduleName), $tenantId);

            if ($exitCode !== 0) {
                $failures[] = $tenantId;
            }
        }

        if ($failures !== []) {
            $this->error('Failed tenants: ' . implode(', ', $failures));
            return self::FAILURE;
        }

        $this->success("Enabled '{$moduleName}' for all tenants.");
        return self::SUCCESS;
    }

}
