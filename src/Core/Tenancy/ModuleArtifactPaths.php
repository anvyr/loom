<?php

declare(strict_types=1);

namespace Anvyr\Loom\Core\Tenancy;

use Anvyr\Loom\Core\Paths;

final class ModuleArtifactPaths
{
    public function __construct(
        private readonly Paths $paths,
        private readonly TenancyState $state,
    ) {
    }

    public function statePath(?string $tenantId = null, ?string $basePath = null): string
    {
        return $this->storageRoot($tenantId, $basePath) . '/modules.json';
    }

    public function compiledPath(?string $tenantId = null, ?string $basePath = null): string
    {
        return $this->storageRoot($tenantId, $basePath) . '/modules-compiled.json';
    }

    public function autoloadPath(?string $tenantId = null, ?string $basePath = null): string
    {
        return $this->storageRoot($tenantId, $basePath) . '/modules-autoload.php';
    }

    /**
     * @return array<int, string>
     */
    public function compiledCandidates(?string $basePath = null): array
    {
        return $this->tenantFirstCandidates('modules-compiled.json', $basePath);
    }

    /**
     * @return array<int, string>
     */
    public function stateCandidates(?string $basePath = null): array
    {
        return $this->tenantFirstCandidates('modules.json', $basePath);
    }

    /**
     * @return array<int, string>
     */
    public function autoloadCandidates(?string $basePath = null): array
    {
        return $this->tenantFirstCandidates('modules-autoload.php', $basePath);
    }

    public function globalStatePath(?string $basePath = null): string
    {
        return $this->basePath($basePath) . '/storage/modules.json';
    }

    public function globalCompiledPath(?string $basePath = null): string
    {
        return $this->basePath($basePath) . '/storage/modules-compiled.json';
    }

    public function globalAutoloadPath(?string $basePath = null): string
    {
        return $this->basePath($basePath) . '/storage/modules-autoload.php';
    }

    private function storageRoot(?string $tenantId = null, ?string $basePath = null): string
    {
        if ($tenantId !== null && $tenantId !== '') {
            return $this->tenantStorageRoot($tenantId, $basePath) . '/modules';
        }

        if ($this->state->isEnabled() && $this->state->currentId() !== null) {
            return $this->tenantStorageRoot((string) $this->state->currentId(), $basePath) . '/modules';
        }

        return $this->basePath($basePath) . '/storage';
    }

    /**
     * @return array<int, string>
     */
    private function tenantFirstCandidates(string $filename, ?string $basePath = null): array
    {
        $paths = [];

        if ($this->state->isEnabled() && $this->state->currentId() !== null) {
            $paths[] = $this->tenantStorageRoot((string) $this->state->currentId(), $basePath) . '/modules/' . $filename;
        }

        $paths[] = $this->basePath($basePath) . '/storage/' . $filename;

        return array_values(array_unique($paths));
    }

    private function tenantStorageRoot(string $tenantId, ?string $basePath = null): string
    {
        $root = (string) ($this->state->config()['paths']['storage_root'] ?? 'storage/tenants');

        if (Paths::isAbsolute($root)) {
            return rtrim($root, '/\\') . DIRECTORY_SEPARATOR . $tenantId;
        }

        return $this->basePath($basePath) . DIRECTORY_SEPARATOR . trim($root, '/\\') . DIRECTORY_SEPARATOR . $tenantId;
    }

    private function basePath(?string $basePath = null): string
    {
        return $basePath !== null && $basePath !== '' ? rtrim($basePath, '/\\') : $this->paths->base();
    }
}
