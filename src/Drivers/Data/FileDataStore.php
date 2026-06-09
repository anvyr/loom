<?php

declare(strict_types=1);

namespace Anvyr\Loom\Drivers\Data;

use Anvyr\Loom\Contracts\DataStore;

class FileDataStore implements DataStore
{
    private string $basePath;

    /** @var array<string, array<string, array<string, mixed>>> */
    private array $cache = [];

    public function __construct(?string $basePath = null)
    {
        $this->basePath = $basePath ?? storage_path('data');

        if (!is_dir($this->basePath)) {
            mkdir($this->basePath, 0755, true);
        }
    }

    public function get(string $collection, string $key): ?array
    {
        if (isset($this->cache[$collection][$key])) {
            return $this->cache[$collection][$key];
        }

        $file = $this->path($collection, $key);

        if (!file_exists($file)) {
            return null;
        }

        $contents = file_get_contents($file);
        $data = is_string($contents) ? json_decode($contents, true) : null;

        if (!is_array($data)) {
            return null;
        }

        $this->cache[$collection][$key] = $data;

        return $data;
    }

    public function put(string $collection, string $key, array $data): void
    {
        $this->ensureDir($collection);

        $data['_key'] = $key;
        $data['_updated_at'] = date('Y-m-d H:i:s');
        $data['_created_at'] ??= $data['_updated_at'];

        $file = $this->path($collection, $key);
        file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);

        $this->cache[$collection][$key] = $data;
    }

    public function forget(string $collection, string $key): bool
    {
        $file = $this->path($collection, $key);

        if (!file_exists($file)) {
            return false;
        }

        unset($this->cache[$collection][$key]);

        return unlink($file);
    }

    public function has(string $collection, string $key): bool
    {
        if (isset($this->cache[$collection][$key])) {
            return true;
        }

        return file_exists($this->path($collection, $key));
    }

    public function all(string $collection): array
    {
        $dir = $this->collectionPath($collection);

        if (!is_dir($dir)) {
            return [];
        }

        $records = [];

        foreach (glob($dir . '/*.json') ?: [] as $file) {
            $key = basename($file, '.json');

            // Skip internal files
            if (str_starts_with($key, '_')) {
                continue;
            }

            $records[$key] = $this->get($collection, $key);
        }

        return array_filter($records);
    }

    public function filter(string $collection, callable $predicate): array
    {
        $all = $this->all($collection);

        return array_filter($all, $predicate);
    }

    public function clear(string $collection): void
    {
        $dir = $this->collectionPath($collection);

        if (!is_dir($dir)) {
            return;
        }

        foreach (glob($dir . '/*.json') ?: [] as $file) {
            unlink($file);
        }

        unset($this->cache[$collection]);

        @rmdir($dir);
    }

    public function driver(): string
    {
        return 'file';
    }

    private function collectionPath(string $collection): string
    {
        return $this->basePath . '/' . $this->sanitize($collection);
    }

    private function path(string $collection, string $key): string
    {
        return $this->collectionPath($collection) . '/' . $this->sanitize($key) . '.json';
    }

    private function sanitize(string $name): string
    {
        return preg_replace('/[^a-zA-Z0-9_-]/', '_', $name) ?? $name;
    }

    private function ensureDir(string $collection): void
    {
        $dir = $this->collectionPath($collection);

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }

    public function clearCache(): void
    {
        $this->cache = [];
    }
}
