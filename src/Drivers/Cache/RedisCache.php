<?php

declare(strict_types=1);

namespace Anvyr\Loom\Drivers\Cache;

use Anvyr\Loom\Contracts\CacheDriver;
use RedisException;

class RedisCache implements CacheDriver
{
    private \Redis $client;
    private string $prefix;

    /** @param array<string, mixed> $config */
    public function __construct(array $config)
    {
        if (!class_exists(\Redis::class)) {
            throw new \RuntimeException('Redis extension is not available.');
        }

        $host = $config['host'] ?? '127.0.0.1';
        $port = (int) ($config['port'] ?? 6379);
        $timeout = (float) ($config['timeout'] ?? 1.5);
        $persistent = (bool) ($config['persistent'] ?? false);
        $this->prefix = rtrim($config['prefix'] ?? 'loom', ':') . ':';

        $client = new \Redis();

        $connected = $persistent
            ? $client->pconnect($host, $port, $timeout)
            : $client->connect($host, $port, $timeout);

        if (!$connected) {
            throw new \RuntimeException(sprintf('Unable to connect to Redis at %s:%d', $host, $port));
        }

        if (!empty($config['password'])) {
            if (!$client->auth((string) $config['password'])) {
                throw new \RuntimeException('Redis authentication failed.');
            }
        }

        if (isset($config['database'])) {
            $client->select((int) $config['database']);
        }

        if (defined('Redis::SERIALIZER_PHP')) {
            $client->setOption(\Redis::OPT_SERIALIZER, \Redis::SERIALIZER_PHP);
        }

        $this->client = $client;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        try {
            $value = $this->client->get($this->prefix($key));
        } catch (RedisException $e) {
            return $default;
        }

        if ($value === false) {
            return $default;
        }

        return $value;
    }

    public function set(string $key, mixed $value, int $ttl = 3600): bool
    {
        $namespacedKey = $this->prefix($key);

        try {
            if ($ttl > 0) {
                return (bool) $this->client->setex($namespacedKey, $ttl, $value);
            }

            return (bool) $this->client->set($namespacedKey, $value);
        } catch (RedisException $e) {
            return false;
        }
    }

    public function has(string $key): bool
    {
        try {
            return (bool) $this->client->exists($this->prefix($key));
        } catch (RedisException $e) {
            return false;
        }
    }

    public function delete(string $key): bool
    {
        try {
            return (bool) $this->client->del($this->prefix($key));
        } catch (RedisException $e) {
            return false;
        }
    }

    public function clear(): bool
    {
        $pattern = $this->prefix . '*';
        $cursor = null;

        try {
            do {
                $keys = $this->client->scan($cursor, $pattern, 1000);
                if ($keys === false) {
                    break;
                }

                if ($keys !== []) {
                    $this->client->del($keys);
                }
            } while ($cursor !== 0);
        } catch (RedisException $e) {
            return false;
        }

        return true;
    }

    public function remember(string $key, int $ttl, callable $callback): mixed
    {
        $sentinel = new \stdClass();
        $existing = $this->get($key, $sentinel);

        if ($existing !== $sentinel) {
            return $existing;
        }

        $value = $callback();
        $this->set($key, $value, $ttl);

        return $value;
    }

    private function prefix(string $key): string
    {
        return $this->prefix . $key;
    }
}
