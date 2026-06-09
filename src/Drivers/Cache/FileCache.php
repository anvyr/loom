<?php

declare(strict_types=1);

namespace Anvyr\Loom\Drivers\Cache;

use Anvyr\Loom\Contracts\CacheDriver;

class FileCache implements CacheDriver
{
    private string $path;
    private string $prefix;

    /** @param array<string, mixed> $config */
    public function __construct(array $config)
    {
        $this->path = $config['path'];
        $this->prefix = $config['prefix'] ?? 'loom';

        if (!is_dir($this->path)) {
            mkdir($this->path, 0755, true);
        }
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $file = $this->getFilePath($key);

        if (!file_exists($file)) {
            return $default;
        }

        $content = file_get_contents($file);
        if ($content === false) {
            return $default;
        }

        $data = @unserialize($content, ['allowed_classes' => true]);

        if (!is_array($data) || !array_key_exists('value', $data)) {
            $this->delete($key);
            return $default;
        }

        $expires = $data['expires'] ?? null;
        if (is_int($expires) && $expires !== 0 && $expires < time()) {
            $this->delete($key);
            return $default;
        }

        return $data['value'];
    }

    public function set(string $key, mixed $value, int $ttl = 3600): bool
    {
        $file = $this->getFilePath($key);
        $dir = dirname($file);

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $expires = $ttl > 0 ? time() + $ttl : 0;

        $data = [
            'value' => $value,
            'expires' => $expires,
        ];

        return file_put_contents($file, serialize($data), LOCK_EX) !== false;
    }

    public function has(string $key): bool
    {
        $sentinel = new \stdClass();
        return $this->get($key, $sentinel) !== $sentinel;
    }

    public function delete(string $key): bool
    {
        $file = $this->getFilePath($key);

        if (!file_exists($file)) {
            return true;
        }

        return unlink($file);
    }

    public function clear(): bool
    {
        if (!is_dir($this->path)) {
            return true;
        }

        $this->flushDirectory($this->path);

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

    private function getFilePath(string $key): string
    {
        $hash = md5($this->prefix . $key);
        $dir = substr($hash, 0, 2);
        return $this->path . '/' . $dir . '/' . $hash;
    }

    private function flushDirectory(string $dir): void
    {
        $items = scandir($dir);

        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $dir . '/' . $item;

            if (is_dir($path) && !is_link($path)) {
                $this->flushDirectory($path);
                rmdir($path);
                continue;
            }

            if (is_file($path) || is_link($path)) {
                unlink($path);
            }
        }
    }
}
