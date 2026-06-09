<?php

declare(strict_types=1);

namespace Anvyr\Loom\Core\Tenancy;

use Anvyr\Loom\Contracts\TenantResolverInterface;
use Anvyr\Loom\Http\Request;

final class TenancyManager
{
    public function __construct(
        private readonly TenancyState $state,
    ) {
    }

    public function bootstrapFromRequest(Request $request): ?TenantContext
    {
        $config = $this->config();
        if (!(bool) ($config['enabled'] ?? false)) {
            $this->state->setCurrent(null);
            return null;
        }

        $context = $this->resolveFromRequest($request, $config);
        if ($context === null) {
            $context = new TenantContext($this->defaultId($config));
        }

        $this->setCurrent($context);

        if ($context->pathPrefix() !== null) {
            $request->setPathPrefix($context->pathPrefix());
        }

        return $context;
    }

    public function bootstrapFromCli(): ?TenantContext
    {
        $config = $this->config();
        if (!(bool) ($config['enabled'] ?? false)) {
            $this->state->setCurrent(null);
            return null;
        }

        $tenantId = env('TENANCY_TENANT', null);
        if (!is_string($tenantId) || $tenantId === '') {
            $tenantId = $this->defaultId($config);
        }

        $context = new TenantContext($tenantId);
        $this->setCurrent($context);
        return $context;
    }

    public function setCurrent(?TenantContext $context): void
    {
        $this->state->setCurrent($context);
    }

    /** @param array<string, mixed> $config */
    public function setConfig(array $config): void
    {
        $this->state->setConfig($config);
    }

    public function current(): ?TenantContext
    {
        return $this->state->current();
    }

    public function currentId(): ?string
    {
        return $this->state->currentId();
    }

    public function isEnabled(): bool
    {
        return $this->state->isEnabled();
    }

    /** @return array<string, mixed> */
    public function config(): array
    {
        return $this->state->config();
    }

    /** @param array<string, mixed>|null $config */
    public function defaultId(?array $config = null): string
    {
        $config ??= $this->config();
        $default = $config['default'] ?? 'default';

        return is_string($default) && $default !== '' ? $default : 'default';
    }

    /** @param array<string, mixed> $config */
    private function resolveFromRequest(Request $request, array $config): ?TenantContext
    {
        $resolver = $config['resolver'] ?? 'host';

        if ($resolver === 'callback') {
            return $this->resolveFromCallback($request, $config);
        }

        if ($resolver === 'path') {
            return $this->resolveFromPath($request, $config);
        }

        return $this->resolveFromHost($request, $config);
    }

    /** @param array<string, mixed> $config */
    private function resolveFromCallback(Request $request, array $config): ?TenantContext
    {
        $resolverClass = $config['callback'] ?? null;
        if (!is_string($resolverClass) || $resolverClass === '' || !class_exists($resolverClass)) {
            return null;
        }

        $resolver = new $resolverClass();
        if (!$resolver instanceof TenantResolverInterface) {
            return null;
        }

        return $resolver->resolve($request, $config);
    }

    /** @param array<string, mixed> $config */
    private function resolveFromHost(Request $request, array $config): ?TenantContext
    {
        $hostConfig = $config['host'] ?? [];
        $host = strtolower($request->host());

        if ($host === '') {
            return null;
        }

        if (!empty($hostConfig['strip_www'])) {
            $host = preg_replace('/^www\./', '', $host) ?? $host;
        }

        $map = $hostConfig['map'] ?? [];
        if (is_array($map) && isset($map[$host])) {
            return new TenantContext((string) $map[$host], $host);
        }

        if (!empty($hostConfig['wildcard_subdomains'])) {
            $rootDomains = array_filter((array) ($hostConfig['root_domains'] ?? []));
            foreach ($rootDomains as $rootDomain) {
                $rootDomain = strtolower((string) $rootDomain);
                $suffix = '.' . $rootDomain;
                if (str_ends_with($host, $suffix)) {
                    $subdomain = substr($host, 0, -strlen($suffix));
                    if ($subdomain !== '' && $subdomain !== $host) {
                        return new TenantContext($subdomain, $host);
                    }
                }
            }
        }

        return null;
    }

    /** @param array<string, mixed> $config */
    private function resolveFromPath(Request $request, array $config): ?TenantContext
    {
        $pathConfig = $config['path'] ?? [];
        $segmentIndex = (int) ($pathConfig['segment'] ?? 1);
        $segmentIndex = max(1, $segmentIndex) - 1;

        $path = trim($request->rawPath(), '/');
        if ($path === '') {
            return null;
        }

        $segments = explode('/', $path);
        if (!isset($segments[$segmentIndex])) {
            return null;
        }

        $segment = $segments[$segmentIndex];
        if ($segment === '') {
            return null;
        }

        $map = $pathConfig['map'] ?? [];
        $tenantId = is_array($map) && isset($map[$segment]) ? (string) $map[$segment] : $segment;

        $prefixSegments = array_slice($segments, 0, $segmentIndex + 1);
        $prefix = '/' . implode('/', $prefixSegments);

        return new TenantContext($tenantId, null, $prefix, $prefix);
    }
}
