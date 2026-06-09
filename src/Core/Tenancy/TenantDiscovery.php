<?php

declare(strict_types=1);

namespace Anvyr\Loom\Core\Tenancy;

use Anvyr\Loom\Core\Paths;

final class TenantDiscovery
{
    public function __construct(
        private readonly Paths $paths,
        private readonly TenancyState $state,
    ) {
    }

    /**
     * @return array<int, string>
     */
    public function discoverTenantIds(): array
    {
        $userRoot = (string) ($this->state->config()['paths']['user_root'] ?? 'user/tenants');
        $root = Paths::isAbsolute($userRoot)
            ? rtrim($userRoot, '/\\')
            : $this->paths->base(trim($userRoot, '/\\'));

        if (!is_dir($root)) {
            return [];
        }

        $entries = scandir($root);
        if ($entries === false) {
            return [];
        }

        $tenants = [];

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            if (!is_dir($root . DIRECTORY_SEPARATOR . $entry)) {
                continue;
            }

            $tenants[] = $entry;
        }

        sort($tenants);

        return array_values(array_unique($tenants));
    }
}
