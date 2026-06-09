<?php

declare(strict_types=1);

namespace Anvyr\Loom\Core;

final class ModuleManifest
{
    public function __construct(
        public readonly string $name,
        public readonly string $version,
        public readonly string $path,
        public readonly string $entry,
        public readonly bool $enabled,
        /** @var array<string, string> */
        public readonly array $requires = [],
        /** @var list<string> */
        public readonly array $conflicts = [],
        /** @var list<string> */
        public readonly array $provides = [],
        /** @var array<string, string> Signature → FQCN */
        public readonly array $commands = [],
        /** @var array<string, string> Type → relative path */
        public readonly array $routes = [],
        public readonly ?string $views = null,
        public readonly ?string $description = null,
        public readonly ?string $stability = null,
        /** @var array<string, mixed> Freeform metadata from module.json "extra" key */
        public readonly array $extra = [],
    ) {
        if ($this->name === '') {
            throw new \InvalidArgumentException('Module manifest: name must not be empty');
        }

        if ($this->path === '') {
            throw new \InvalidArgumentException('Module manifest: path must not be empty');
        }

        if ($this->entry === '') {
            throw new \InvalidArgumentException("Module manifest '{$this->name}': entry must not be empty");
        }
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(string $name, array $data, bool $enabled = true): self
    {
        return new self(
            name: (string) ($data['name'] ?? $name),
            version: (string) ($data['version'] ?? '0.0.0'),
            path: (string) ($data['path'] ?? ''),
            entry: (string) ($data['entry'] ?? ''),
            enabled: $enabled,
            requires: self::normalizeRequires(is_array($data['requires'] ?? null) ? $data['requires'] : []),
            conflicts: self::normalizeStringList(is_array($data['conflicts'] ?? null) ? array_values($data['conflicts']) : []),
            provides: self::normalizeStringList(is_array($data['provides'] ?? null) ? array_values($data['provides']) : []),
            commands: is_array($data['commands'] ?? null) ? self::normalizeCommands($data['commands']) : [],
            routes: is_array($data['routes'] ?? null) ? self::normalizeRoutes($data['routes']) : [],
            views: isset($data['views']) ? (string) $data['views'] : null,
            description: isset($data['description']) ? (string) $data['description'] : null,
            stability: isset($data['stability']) ? (string) $data['stability'] : null,
            extra: is_array($data['extra'] ?? null) ? $data['extra'] : [],
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'version' => $this->version,
            'path' => $this->path,
            'entry' => $this->entry,
            'enabled' => $this->enabled,
            'requires' => $this->requires,
            'conflicts' => $this->conflicts,
            'provides' => $this->provides,
            'commands' => $this->commands,
            'routes' => $this->routes,
            'views' => $this->views,
            'description' => $this->description,
            'stability' => $this->stability,
            'extra' => $this->extra,
        ];
    }

    /**
     * @param array<mixed, mixed> $requires
     * @return array<string, string>
     */
    private static function normalizeRequires(array $requires): array
    {
        $normalized = [];

        foreach ($requires as $dep => $constraint) {
            if (!is_string($dep) || $dep === '') {
                continue;
            }
            $normalized[$dep] = is_scalar($constraint) ? (string) $constraint : '';
        }

        return $normalized;
    }

    /**
     * @param array<mixed> $items
     * @return list<string>
     */
    private static function normalizeStringList(array $items): array
    {
        $out = [];
        foreach ($items as $item) {
            if (is_string($item) && $item !== '') {
                $out[] = $item;
            }
        }
        return $out;
    }

    /**
     * @param array<mixed, mixed> $commands
     * @return array<string, string>
     */
    private static function normalizeCommands(array $commands): array
    {
        $normalized = [];

        foreach ($commands as $signature => $class) {
            if (!is_string($signature) || $signature === '' || !is_string($class) || $class === '') {
                continue;
            }
            $normalized[$signature] = $class;
        }

        return $normalized;
    }

    /**
     * @param array<mixed, mixed> $routes
     * @return array<string, string>
     */
    private static function normalizeRoutes(array $routes): array
    {
        $normalized = [];
        $allowed = ['web', 'api'];

        foreach ($routes as $type => $path) {
            if (!is_string($type) || !in_array($type, $allowed, true)) {
                continue;
            }
            if (!is_string($path) || $path === '') {
                continue;
            }
            $normalized[$type] = $path;
        }

        return $normalized;
    }
}
