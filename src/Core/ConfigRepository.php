<?php

declare(strict_types=1);

namespace Anvyr\Loom\Core;

use Closure;
use RuntimeException;

class ConfigRepository
{
    private readonly string $configPath;
    private readonly string $userConfigPath;
    private readonly string|Closure|null $tenantConfigPath;

    /** @var array<string, mixed> */
    private array $items = [];

    /** @var array<string, bool> */
    private array $loaded = [];

    /** @var array<string, string> Namespace → absolute config directory path */
    private array $namespacePaths = [];

    private bool $loadedFromCache = false;

    private ?string $cacheFile;

    public function __construct(
        string $configPath,
        ?string $cacheFile = null,
        ?string $userConfigPath = null,
        string|Closure|null $tenantConfigPath = null,
    ) {
        $this->configPath = rtrim($configPath, DIRECTORY_SEPARATOR);
        $this->cacheFile = $cacheFile;
        $this->userConfigPath = rtrim(
            $userConfigPath ?? (dirname($this->configPath) . DIRECTORY_SEPARATOR . 'user' . DIRECTORY_SEPARATOR . 'config'),
            DIRECTORY_SEPARATOR
        );
        $this->tenantConfigPath = is_string($tenantConfigPath)
            ? rtrim($tenantConfigPath, DIRECTORY_SEPARATOR)
            : $tenantConfigPath;

        if ($cacheFile && file_exists($cacheFile)) {
            $cached = require $cacheFile;
            if (is_array($cached)) {
                $this->items = $cached;
                foreach (array_keys($cached) as $name) {
                    $this->loaded[$name] = true;
                }
                $this->loadedFromCache = true;
            }
        }
    }

    public function registerNamespace(string $namespace, string $configPath): void
    {
        $this->namespacePaths[$namespace] = rtrim($configPath, DIRECTORY_SEPARATOR);
    }

    /** @return array<string, string> */
    public function getNamespacePaths(): array
    {
        return $this->namespacePaths;
    }

    public function has(string $key): bool
    {
        $sentinel = new \stdClass();
        return $this->get($key, $sentinel) !== $sentinel;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        if (str_contains($key, ':')) {
            return $this->getNamespaced($key, $default);
        }

        if (!str_contains($key, '.')) {
            $this->load($key);
            return $this->items[$key] ?? $default;
        }

        [$name, $path] = explode('.', $key, 2);
        $this->load($name);

        return $this->resolve($this->items[$name] ?? null, $path, $default);
    }

    public function set(string $key, mixed $value): void
    {
        if (str_contains($key, ':')) {
            $this->setNamespaced($key, $value);
            return;
        }

        if (!str_contains($key, '.')) {
            $this->items[$key] = $value;
            $this->loaded[$key] = true;
            return;
        }

        [$name, $path] = explode('.', $key, 2);
        $this->load($name);

        $this->assignNested($this->items, $name, $path, $value);
    }

    public function loadAll(): void
    {
        if ($this->loadedFromCache) {
            return;
        }

        foreach (glob($this->configPath . '/*.php') ?: [] as $file) {
            $name = basename($file, '.php');
            $this->load($name);
        }

        foreach ($this->namespacePaths as $namespace => $path) {
            $pattern = $path . DIRECTORY_SEPARATOR . '*.php';
            foreach (glob($pattern) ?: [] as $file) {
                $name = basename($file, '.php');
                $this->loadNamespaced($namespace, $name);
            }
        }
    }

    public function cacheTo(string $destination): void
    {
        $this->loadAll();

        $directory = dirname($destination);
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $export = var_export($this->items, true);
        $content = <<<PHP
<?php

return {$export};
PHP;

        file_put_contents($destination, $content);

        $this->cacheFile = $destination;
    }

    public function clearCache(): void
    {
        if ($this->cacheFile && file_exists($this->cacheFile)) {
            unlink($this->cacheFile);
        }
    }

    /** @return array<string, mixed> */
    public function all(): array
    {
        $this->loadAll();
        return $this->items;
    }

    private function load(string $name): void
    {
        if (isset($this->loaded[$name]) || $this->loadedFromCache) {
            return;
        }

        $path = $this->configPath . DIRECTORY_SEPARATOR . $name . '.php';

        if (!file_exists($path)) {
            $this->items[$name] = [];
            $this->loaded[$name] = true;
            return;
        }

        $config = require $path;
        if (!is_array($config)) {
            throw new RuntimeException("Configuration file '{$path}' must return an array.");
        }

        $userConfigFile = $this->userConfigPath . DIRECTORY_SEPARATOR . $name . '.php';
        if (file_exists($userConfigFile)) {
            $userConfig = require $userConfigFile;
            if (is_array($userConfig)) {
                $config = array_replace_recursive($config, $userConfig);
            }
        }

        $tenantConfigPath = $this->resolveTenantConfigPath();
        if ($tenantConfigPath !== null) {
            $tenantConfigFile = $tenantConfigPath . DIRECTORY_SEPARATOR . $name . '.php';
            if (file_exists($tenantConfigFile)) {
                $tenantConfig = require $tenantConfigFile;
                if (is_array($tenantConfig)) {
                    $config = array_replace_recursive($config, $tenantConfig);
                }
            }
        }

        $this->items[$name] = $config;
        $this->loaded[$name] = true;
    }

    private function loadNamespaced(string $namespace, string $file): void
    {
        $cacheKey = $namespace . ':' . $file;

        if (isset($this->loaded[$cacheKey]) || $this->loadedFromCache) {
            return;
        }

        $config = [];

        $modulePath = $this->namespacePaths[$namespace] ?? null;
        if ($modulePath !== null) {
            $moduleFile = $modulePath . DIRECTORY_SEPARATOR . $file . '.php';
            if (file_exists($moduleFile)) {
                $loaded = require $moduleFile;
                if (is_array($loaded)) {
                    $config = $loaded;
                }
            }
        }

        $userFile = $this->userConfigPath . DIRECTORY_SEPARATOR . $namespace . DIRECTORY_SEPARATOR . $file . '.php';
        if (file_exists($userFile)) {
            $userConfig = require $userFile;
            if (is_array($userConfig)) {
                $config = array_replace_recursive($config, $userConfig);
            }
        }

        $tenantConfigPath = $this->resolveTenantConfigPath();
        if ($tenantConfigPath !== null) {
            $tenantFile = $tenantConfigPath . DIRECTORY_SEPARATOR . $namespace . DIRECTORY_SEPARATOR . $file . '.php';
            if (file_exists($tenantFile)) {
                $tenantConfig = require $tenantFile;
                if (is_array($tenantConfig)) {
                    $config = array_replace_recursive($config, $tenantConfig);
                }
            }
        }

        $this->items[$cacheKey] = $config;
        $this->loaded[$cacheKey] = true;
    }

    private function getNamespaced(string $key, mixed $default): mixed
    {
        [$namespace, $rest] = explode(':', $key, 2);

        if (!str_contains($rest, '.')) {
            $this->loadNamespaced($namespace, $rest);
            return $this->items[$namespace . ':' . $rest] ?? $default;
        }

        [$file, $path] = explode('.', $rest, 2);
        $cacheKey = $namespace . ':' . $file;
        $this->loadNamespaced($namespace, $file);

        return $this->resolve($this->items[$cacheKey] ?? null, $path, $default);
    }

    private function setNamespaced(string $key, mixed $value): void
    {
        [$namespace, $rest] = explode(':', $key, 2);

        if (!str_contains($rest, '.')) {
            $cacheKey = $namespace . ':' . $rest;
            $this->items[$cacheKey] = $value;
            $this->loaded[$cacheKey] = true;
            return;
        }

        [$file, $path] = explode('.', $rest, 2);
        $cacheKey = $namespace . ':' . $file;
        $this->loadNamespaced($namespace, $file);

        $this->assignNested($this->items, $cacheKey, $path, $value);
    }

    private function resolve(mixed $value, string $path, mixed $default): mixed
    {
        foreach (explode('.', $path) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }

        return $value;
    }

    /** @param array<string, mixed> $items */
    private function assignNested(array &$items, string $rootKey, string $path, mixed $value): void
    {
        if (!isset($items[$rootKey]) || !is_array($items[$rootKey])) {
            $items[$rootKey] = [];
        }

        $segments = explode('.', $path);
        $reference = &$items[$rootKey];

        foreach ($segments as $segment) {
            if (!is_array($reference)) {
                $reference = [];
            }
            if (!array_key_exists($segment, $reference)) {
                $reference[$segment] = [];
            }
            $reference = &$reference[$segment];
        }

        $reference = $value;
    }

    public function persist(string $key, mixed $value): string
    {
        if (str_contains($key, ':')) {
            return $this->persistNamespaced($key, $value);
        }

        if (!str_contains($key, '.')) {
            throw new \InvalidArgumentException("Cannot set entire config file to a single value. Use 'file.key' notation.");
        }

        [$file, $path] = explode('.', $key, 2);

        $filePath = $this->userConfigPath . DIRECTORY_SEPARATOR . $file . '.php';

        return $this->writePersist($filePath, $path, $value, $key);
    }

    private function persistNamespaced(string $key, mixed $value): string
    {
        [$namespace, $rest] = explode(':', $key, 2);

        if (!str_contains($rest, '.')) {
            throw new \InvalidArgumentException("Cannot set entire config file to a single value. Use 'namespace:file.key' notation.");
        }

        [$file, $path] = explode('.', $rest, 2);

        $dir = $this->userConfigPath . DIRECTORY_SEPARATOR . $namespace;
        $filePath = $dir . DIRECTORY_SEPARATOR . $file . '.php';

        return $this->writePersist($filePath, $path, $value, $key);
    }

    private function writePersist(string $filePath, string $path, mixed $value, string $originalKey): string
    {
        $dir = dirname($filePath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $config = [];
        if (file_exists($filePath)) {
            $config = require $filePath;
            if (!is_array($config)) {
                $config = [];
            }
        }

        $this->setNested($config, $path, $value);

        $content = "<?php\n\nreturn " . var_export($config, true) . ";\n";
        file_put_contents($filePath, $content);

        $this->set($originalKey, $value);

        return $filePath;
    }

    /** @param array<string, mixed> $array */
    private function setNested(array &$array, string $path, mixed $value): void
    {
        $keys = explode('.', $path);
        $current = &$array;
        foreach ($keys as $i => $key) {
            if ($i === count($keys) - 1) {
                $current[$key] = $value;
            } else {
                if (!isset($current[$key]) || !is_array($current[$key])) {
                    $current[$key] = [];
                }
                $current = &$current[$key];
            }
        }
    }

    private function resolveTenantConfigPath(): ?string
    {
        if ($this->tenantConfigPath instanceof Closure) {
            $resolved = ($this->tenantConfigPath)();

            return is_string($resolved) && $resolved !== ''
                ? rtrim($resolved, DIRECTORY_SEPARATOR)
                : null;
        }

        return is_string($this->tenantConfigPath) && $this->tenantConfigPath !== ''
            ? rtrim($this->tenantConfigPath, DIRECTORY_SEPARATOR)
            : null;
    }
}
