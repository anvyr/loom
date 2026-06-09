<?php

declare(strict_types=1);

namespace Anvyr\Loom\Support\Cache;

use Anvyr\Loom\Contracts\CacheDriver;
use stdClass;

final class CacheTagManager
{
    private const INDEX_PREFIX = 'cache:tags:';

    public function __construct(
        private readonly CacheDriver $cache
    ) {
    }

    /** @param string|string[] $tags */
    public function remember(string|array $tags, string $key, int $ttl, callable $callback): mixed
    {
        $sentinel = new stdClass();
        $value = $this->cache->get($key, $sentinel);

        if ($value !== $sentinel) {
            return $value;
        }

        $value = $callback();
        $this->set($tags, $key, $value, $ttl);

        return $value;
    }

    /** @param string|string[] $tags */
    public function set(string|array $tags, string $key, mixed $value, int $ttl = 0): void
    {
        $this->cache->set($key, $value, $ttl);
        $this->indexKey((array) $tags, $key, $ttl);
    }

    public function delete(string $key): void
    {
        $this->cache->delete($key);
    }

    /** @param string|string[] $tags */
    public function flush(string|array $tags): void
    {
        foreach ((array) $tags as $tag) {
            $indexKey = self::INDEX_PREFIX . $tag;
            $keys = $this->cache->get($indexKey, []);

            if (is_array($keys)) {
                foreach ($keys as $cacheKey) {
                    if (is_string($cacheKey)) {
                        $this->cache->delete($cacheKey);
                    }
                }
            }

            $this->cache->delete($indexKey);
        }
    }

    /** @param string[] $tags */
    private function indexKey(array $tags, string $key, int $ttl): void
    {
        foreach ($tags as $tag) {
            $indexKey = self::INDEX_PREFIX . $tag;
            $existing = $this->cache->get($indexKey, []);

            if (!is_array($existing)) {
                $existing = [];
            }

            if (!in_array($key, $existing, true)) {
                $existing[] = $key;
                $this->cache->set($indexKey, $existing, 31536000);
            }
        }
    }
}
