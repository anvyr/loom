<?php

declare(strict_types=1);

namespace Anvyr\Loom\Commands\Module;

use Anvyr\Loom\Commands\Command;
use Anvyr\Loom\Commands\Concerns\InteractsWithTenancy;
use Anvyr\Loom\Core\Tenancy\ModuleArtifactPaths;

class ProvisionModuleCommand extends Command
{
    use InteractsWithTenancy;

    public static function category(): string
    {
        return 'Modules';
    }

    public function signature(): string
    {
        return 'module:provision [--tenant=] [--all-tenants] [--dry-run]';
    }

    public function description(): string
    {
        return 'Provision tenant-scoped module artifacts from global state';
    }

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run', false);
        $artifactPaths = app(ModuleArtifactPaths::class);

        try {
            $tenants = $this->resolveTenantSelection(allowAllTenants: true, fallbackToCurrentTenant: true);
        } catch (\RuntimeException $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }

        if ($tenants === []) {
            $this->warning('No tenants resolved. Use --tenant=<id> or --all-tenants.');
            return self::FAILURE;
        }

        $this->info('Migrating module artifacts to tenant scope...');

        $globalState = $artifactPaths->globalStatePath();
        $globalCompiled = $artifactPaths->globalCompiledPath();
        $globalAutoload = $artifactPaths->globalAutoloadPath();

        if (!file_exists($globalState) && !file_exists($globalCompiled) && !file_exists($globalAutoload)) {
            $this->warning('No global module artifacts found to migrate.');
            return self::SUCCESS;
        }

        foreach ($tenants as $tenantId) {
            $this->line();
            $this->line("\033[1m[tenant: {$tenantId}]\033[0m");

            $tenantState = $artifactPaths->statePath($tenantId);
            $tenantCompiled = $artifactPaths->compiledPath($tenantId);
            $tenantAutoload = $artifactPaths->autoloadPath($tenantId);

            $this->ensureDir(dirname($tenantState), $dryRun);

            if (file_exists($globalState)) {
                $this->migrateState($globalState, $tenantState, $dryRun);
            }

            if (file_exists($globalCompiled)) {
                $this->copyIfMissing($globalCompiled, $tenantCompiled, $dryRun, 'compiled manifest');
            }

            if (file_exists($globalAutoload)) {
                $this->copyIfMissing($globalAutoload, $tenantAutoload, $dryRun, 'autoload file');
            }
        }

        if ($dryRun) {
            $this->success('Dry run complete. No files changed.');
            return self::SUCCESS;
        }

        $this->success('Module artifact migration completed.');
        return self::SUCCESS;
    }

    private function ensureDir(string $path, bool $dryRun): void
    {
        if (is_dir($path)) {
            return;
        }

        if ($dryRun) {
            $this->line("  [dry-run] mkdir -p {$path}");
            return;
        }

        mkdir($path, 0755, true);
        $this->line("  created: {$path}");
    }

    private function migrateState(string $source, string $target, bool $dryRun): void
    {
        $sourceData = json_decode((string) file_get_contents($source), true);
        $sourceEnabled = is_array($sourceData) ? (array) ($sourceData['enabled'] ?? []) : [];

        $targetData = [];
        if (file_exists($target)) {
            $decoded = json_decode((string) file_get_contents($target), true);
            $targetData = is_array($decoded) ? $decoded : [];
        }

        $targetEnabled = (array) ($targetData['enabled'] ?? []);
        $merged = array_values(array_unique(array_merge($targetEnabled, $sourceEnabled)));
        $targetData['enabled'] = $merged;

        if ($dryRun) {
            $this->line("  [dry-run] merge state: {$target}");
            return;
        }

        file_put_contents($target, json_encode($targetData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        $this->line("  merged state -> {$target}");
    }

    private function copyIfMissing(string $source, string $target, bool $dryRun, string $label): void
    {
        if (file_exists($target)) {
            $this->line("  skip {$label} (exists): {$target}");
            return;
        }

        if ($dryRun) {
            $this->line("  [dry-run] copy {$label}: {$source} -> {$target}");
            return;
        }

        copy($source, $target);
        $this->line("  copied {$label} -> {$target}");
    }
}
