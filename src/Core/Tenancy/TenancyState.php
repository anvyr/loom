<?php

declare(strict_types=1);

namespace Anvyr\Loom\Core\Tenancy;

final class TenancyState
{
    /** @param array<string, mixed> $config */
    public function __construct(
        private array $config = [],
        private ?TenantContext $current = null,
    ) {
    }

    public static function fromConfigFile(string $configFile): self
    {
        if (!file_exists($configFile)) {
            return new self();
        }

        $config = require $configFile;

        return new self(is_array($config) ? $config : []);
    }

    /** @return array<string, mixed> */
    public function config(): array
    {
        return $this->config;
    }

    /** @param array<string, mixed> $config */
    public function setConfig(array $config): void
    {
        $this->config = $config;
        $this->current = null;
    }

    public function isEnabled(): bool
    {
        return (bool) ($this->config['enabled'] ?? false);
    }

    public function defaultId(): string
    {
        $default = $this->config['default'] ?? 'default';

        return is_string($default) && $default !== '' ? $default : 'default';
    }

    public function current(): ?TenantContext
    {
        return $this->current;
    }

    public function currentId(): ?string
    {
        return $this->current?->id();
    }

    public function setCurrent(?TenantContext $context): void
    {
        $this->current = $context;
    }
}
