<?php

declare(strict_types=1);

namespace Anvyr\Loom\Commands\Make;

use Anvyr\Loom\Commands\Command;

abstract class GeneratorCommand extends Command
{
    protected function formatClassName(string $name): string
    {
        $name = str_replace(['/', '\\'], '/', $name);
        $parts = explode('/', $name);
        $className = array_pop($parts);
        return ucfirst($className);
    }

    protected function ensureDirectoryExists(string $path): void
    {
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }

    /** @param array<string, string> $replacements */
    protected function replacePlaceholders(string $stub, array $replacements): string
    {
        return str_replace(array_keys($replacements), array_values($replacements), $stub);
    }

    protected function getPath(string $namespace, string $className): string
    {
        $base = 'src/';
        $relative = str_replace('Anvyr\\Loom\\', '', $namespace);
        $relative = str_replace('\\', '/', $relative);

        return base_path("{$base}{$relative}/{$className}.php");
    }

    protected function generateClass(string $name, string $namespace, string $stubContent): int
    {
        $className = $this->formatClassName($name);
        $path = $this->getPath($namespace, $className);

        if (file_exists($path)) {
            $this->error("File '{$className}' already exists.");
            return 1;
        }

        $content = $this->replacePlaceholders($stubContent, [
            '{{ namespace }}' => $namespace,
            '{{ class }}' => $className,
            '{{ name }}' => $name,
        ]);

        $this->ensureDirectoryExists($path);

        if (file_put_contents($path, $content)) {
            $this->success("Created [{$path}]");
            return 0;
        }

        $this->error('Failed to create file.');
        return 1;
    }
}
