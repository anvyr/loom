<?php

declare(strict_types=1);

namespace Anvyr\Loom\Services;

use Anvyr\Loom\Contracts\FilesystemInterface;
use Anvyr\Loom\Drivers\Storage\LocalDriver;
use InvalidArgumentException;

class StorageManager
{
    /** @var array<string, FilesystemInterface> */
    private array $disks = [];

    /** @var array{default?: string, disks: array<string, array<string, mixed>>} */
    private array $config;

    /** @param array{default?: string, disks: array<string, array<string, mixed>>} $config */
    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function disk(?string $name = null): FilesystemInterface
    {
        $name = $name ?? $this->config['default'] ?? 'local';

        if (!isset($this->disks[$name])) {
            $this->disks[$name] = $this->resolve($name);
        }

        return $this->disks[$name];
    }

    private function resolve(string $name): FilesystemInterface
    {
        $config = $this->config['disks'][$name] ?? null;

        if ($config === null) {
            throw new InvalidArgumentException("Storage disk [{$name}] is not configured.");
        }

        $driver = $config['driver'] ?? 'local';

        return match ($driver) {
            'local' => new LocalDriver($config),
            default => throw new InvalidArgumentException("Driver [{$driver}] is not supported."),
        };
    }

    /** @param list<mixed> $parameters */
    public function __call(string $method, array $parameters): mixed
    {
        return $this->disk()->$method(...$parameters);
    }
}
