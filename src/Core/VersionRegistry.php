<?php

declare(strict_types=1);

namespace Anvyr\Loom\Core;

use Anvyr\Loom\Core\Tenancy\ModuleArtifactPaths;
use Composer\Semver\Comparator;
use Composer\Semver\Semver;
use Composer\Semver\VersionParser;

final class VersionRegistry
{
    private VersionParser $parser;

    public function __construct(
        private readonly ConfigRepository $configRepository,
        private readonly ModuleArtifactPaths $artifactPaths,
    ) {
        $this->parser = new VersionParser();
    }

    /** @return array<string, array<string, mixed>> */
    private function loadCompiledModules(): array
    {
        $path = null;
        foreach ($this->artifactPaths->compiledCandidates() as $candidate) {
            if (is_file($candidate)) {
                $path = $candidate;
                break;
            }
        }

        if ($path === null) {
            return [];
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        if (!is_array($decoded) || !isset($decoded['modules']) || !is_array($decoded['modules'])) {
            return [];
        }

        $modules = [];

        foreach ($decoded['modules'] as $module) {
            if (!is_array($module)) {
                continue;
            }

            $name = $module['name'] ?? null;

            if (!is_string($name) || $name === '') {
                continue;
            }

            if (array_key_exists('enabled', $module) && !$module['enabled']) {
                continue;
            }

            $modules[$name] = array_filter([
                'version' => $module['version'] ?? null,
                'stability' => $module['stability'] ?? null,
                'requires' => $module['requires'] ?? [],
                'provides' => $module['provides'] ?? [],
                'description' => $module['description'] ?? null,
                'source' => $module['source'] ?? null,
            ], static fn ($value) => $value !== null && $value !== []);
        }

        return $modules;
    }

    /** @return array<string, mixed> */
    public function getComponent(string $name = 'core'): array
    {
        $manifest = $this->manifest();

        if ($name === 'core') {
            $core = $manifest['core'] ?? [];

            return is_array($core) ? $core : [];
        }

        $modules = $manifest['modules'] ?? [];

        return is_array($modules) && isset($modules[$name]) && is_array($modules[$name])
            ? $modules[$name]
            : [];
    }

    public function getVersion(string $name = 'core'): string
    {
        $component = $this->getComponent($name);

        return (string) ($component['version'] ?? '0.0.0');
    }

    public function getReleaseDate(string $name = 'core'): ?string
    {
        $component = $this->getComponent($name);

        return isset($component['release_date']) ? (string) $component['release_date'] : null;
    }

    public function getStability(string $name = 'core'): string
    {
        $component = $this->getComponent($name);

        if (!empty($component['stability'])) {
            return (string) $component['stability'];
        }

        return $this->inferStability($this->getVersion($name));
    }

    /** @return array<string, array<string, mixed>> */
    public function getModules(): array
    {
        $modules = $this->manifest()['modules'] ?? [];

        return is_array($modules) ? $modules : [];
    }

    public function hasModule(string $name): bool
    {
        $modules = $this->manifest()['modules'] ?? [];

        return isset($modules[$name]);
    }

    /** @return array<string, array<string, mixed>> */
    public function getModulesRequiringCore(string $constraint): array
    {
        $matching = [];
        foreach ($this->getModules() as $name => $meta) {
            $requires = $meta['requires']['core'] ?? null;
            if ($requires && $this->satisfies($this->getVersion('core'), $requires)) {
                $matching[$name] = $meta;
            }
        }
        return $matching;
    }

    /** @return list<string> */
    public function checkModuleRequirements(string $module): array
    {
        $issues = [];
        $moduleMeta = $this->getComponent($module);

        if (empty($moduleMeta)) {
            $issues[] = "Module '{$module}' not found";
            return $issues;
        }

        $requires = $moduleMeta['requires'] ?? [];
        foreach ($requires as $dependency => $constraint) {
            if ($dependency === 'core') {
                if (!$this->satisfies($this->getVersion('core'), $constraint)) {
                    $issues[] = "Core version {$this->getVersion('core')} does not satisfy requirement {$constraint}";
                }
            } elseif ($dependency === 'php') {
                if (!$this->satisfies(PHP_VERSION, $constraint)) {
                    $issues[] = 'PHP version ' . PHP_VERSION . " does not satisfy requirement {$constraint}";
                }
            } else {
                if (!$this->hasModule($dependency)) {
                    $issues[] = "Required module '{$dependency}' is not installed";
                } elseif (!$this->satisfies($this->getVersion($dependency), $constraint)) {
                    $issues[] = "Module '{$dependency}' version {$this->getVersion($dependency)} does not satisfy requirement {$constraint}";
                }
            }
        }

        return $issues;
    }

    public function satisfies(string $version, string $constraint): bool
    {
        try {
            $this->parser->parseConstraints($constraint);

            return Semver::satisfies($version, $constraint);
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function isCompatible(string $module, string $target = 'core', ?string $targetVersion = null): bool
    {
        $moduleMeta = $this->getComponent($module);
        if ($moduleMeta === []) {
            return false;
        }

        $requirements = $moduleMeta['requires'] ?? [];
        if (!is_array($requirements) || !isset($requirements[$target])) {
            return true;
        }

        $constraint = (string) $requirements[$target];
        $version = $targetVersion ?? $this->getVersion($target);

        return $this->satisfies($version, $constraint);
    }

    public function isNewerThan(string $version, string $name = 'core'): bool
    {
        try {
            return Comparator::greaterThan($this->getVersion($name), $version);
        } catch (\Throwable $e) {
            return version_compare($this->getVersion($name), $version, '>');
        }
    }

    public function isPreRelease(string $name = 'core'): bool
    {
        $stability = strtolower($this->getStability($name));

        if ($stability === '') {
            $stability = $this->inferStability($this->getVersion($name));
        }

        return $stability !== 'stable';
    }

    private function inferStability(string $version): string
    {
        if ($version === '') {
            return 'unknown';
        }

        if (str_contains($version, '-')) {
            $parts = explode('-', $version, 2);
            return strtolower($parts[1]);
        }

        return 'stable';
    }

    /** @return array<string, mixed> */
    private function manifest(): array
    {
        $manifest = $this->configRepository->get('version', []);

        if (!is_array($manifest)) {
            $manifest = [];
        }

        $compiled = $this->loadCompiledModules();
        if ($compiled !== []) {
            $current = $manifest['modules'] ?? [];
            $manifest['modules'] = array_merge(is_array($current) ? $current : [], $compiled);
        }

        return $manifest;
    }
}
