<?php

declare(strict_types=1);

namespace Anvyr\Loom\Commands\Concerns;

use Anvyr\Loom\Core\Tenancy\ModuleArtifactPaths;
use Anvyr\Loom\Core\Tenancy\TenancyState;
use Anvyr\Loom\Core\Tenancy\TenantDiscovery;

trait InteractsWithTenancy
{
    /**
     * @return array<int, string>
     */
    protected function resolveTenantSelection(bool $allowAllTenants = true, bool $fallbackToCurrentTenant = true): array
    {
        $tenancyState = app(TenancyState::class);

        if (!$tenancyState->isEnabled()) {
            throw new \RuntimeException('Tenancy must be enabled.');
        }

        $tenantOption = $this->option('tenant');
        $allTenants = (bool) $this->option('all-tenants', false);

        if (is_string($tenantOption) && $tenantOption !== '' && $allTenants) {
            throw new \RuntimeException('Use either --tenant or --all-tenants, not both.');
        }

        if (is_string($tenantOption) && $tenantOption !== '') {
            return [$tenantOption];
        }

        if ($allTenants) {
            if (!$allowAllTenants) {
                throw new \RuntimeException('--all-tenants is not supported by this command.');
            }

            return app(TenantDiscovery::class)->discoverTenantIds();
        }

        if ($fallbackToCurrentTenant && $tenancyState->currentId() !== null) {
            return [(string) $tenancyState->currentId()];
        }

        return [];
    }

    protected function runLoomSubcommand(string $basePath, string $command, ?string $tenantId = null): int
    {
        $cli = build_cli_command(PHP_BINARY, $basePath . '/loom', $command);

        if ($tenantId !== null && $tenantId !== '') {
            $cli = 'TENANCY_TENANT=' . escapeshellarg($tenantId) . ' ' . $cli;
        }

        passthru($cli, $exitCode);

        return (int) $exitCode;
    }

    protected function resolveStatePathForRead(string $basePath): string
    {
        $artifactPaths = app(ModuleArtifactPaths::class);

        foreach ($artifactPaths->stateCandidates($basePath) as $candidate) {
            if (file_exists($candidate)) {
                return $candidate;
            }
        }

        return $artifactPaths->statePath(basePath: $basePath);
    }
}
