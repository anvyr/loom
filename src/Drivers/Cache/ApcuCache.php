<?php

declare(strict_types=1);

namespace Anvyr\Loom\Drivers\Cache;

use Anvyr\Loom\Contracts\CacheDriver;

class ApcuCache implements CacheDriver
{
    private string $prefix;

    /** @param array<string, mixed> $config */
    public function __construct(array $config)
    {
        if (!extension_loaded('apcu')) {
            throw new \RuntimeException('APCu extension is not loaded.');
        }
        $this->prefix = $config['prefix'] ?? 'loom';
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $success = false;
        $value = apcu_fetch($this->getPrefix() . $key, $success);

        return $success ? $value : $default;
    }

    public function set(string $key, mixed $value, int $ttl = 3600): bool
    {
        return apcu_store($this->getPrefix() . $key, $value, $ttl);
    }

    public function has(string $key): bool
    {
        return apcu_exists($this->getPrefix() . $key);
    }

    public function delete(string $key): bool
    {
        return apcu_delete($this->getPrefix() . $key);
    }

    public function clear(): bool
    {
        $prefix = $this->getPrefix();
        $iterator = new \APCUIterator('/^' . preg_quote($prefix, '/') . '/');

        foreach ($iterator as $item) {
            apcu_delete($item['key']);
        }

        return true;
    }

    public function remember(string $key, int $ttl, callable $callback): mixed
    {
        $sentinel = new \stdClass();
        $value = $this->get($key, $sentinel);

        if ($value !== $sentinel) {
            return $value;
        }

        $value = $callback();
        $this->set($key, $value, $ttl);

        return $value;
    }

    private function getPrefix(): string
    {
        return $this->prefix . ':';
    }
}
