<?php

declare(strict_types=1);

namespace Anvyr\Loom\Commands\Config;

use Anvyr\Loom\Commands\Command;
use Anvyr\Loom\Core\ConfigRepository;

class ListCommand extends Command
{
    public function __construct(
        private readonly ConfigRepository $config
    ) {
    }

    public static function category(): string
    {
        return 'Config';
    }

    public function signature(): string
    {
        return 'config:list [namespace]';
    }

    public function description(): string
    {
        return 'List configuration values (optionally filtered by namespace)';
    }

    public function handle(): int
    {
        $namespace = $this->argument(0);
        $all = $this->config->all();

        if ($namespace !== null) {
            $filtered = $this->filterByNamespace($all, (string) $namespace);

            if ($filtered === []) {
                $this->line("No configuration found for namespace '{$namespace}'.");
                return 0;
            }

            $this->line("\033[1mNamespace: {$namespace}\033[0m");
            $this->line();
            $this->printArray($filtered);
            return 0;
        }

        $core = [];
        $namespaced = [];

        foreach ($all as $key => $value) {
            if (str_contains((string) $key, ':')) {
                $namespaced[$key] = $value;
            } else {
                $core[$key] = $value;
            }
        }

        if ($core !== []) {
            $this->line("\033[1mCore\033[0m");
            $this->line();
            $this->printArray($core);
        }

        $grouped = [];
        foreach ($namespaced as $key => $value) {
            [$ns] = explode(':', (string) $key, 2);
            $grouped[$ns][$key] = $value;
        }

        foreach ($grouped as $ns => $items) {
            $this->line();
            $this->line("\033[1mModule: {$ns}\033[0m");
            $this->line();
            $this->printArray($items);
        }

        return 0;
    }

    /**
     * @param array<string, mixed> $all
     * @return array<string, mixed>
     */
    private function filterByNamespace(array $all, string $namespace): array
    {
        $prefix = $namespace . ':';
        $filtered = [];

        foreach ($all as $key => $value) {
            if (str_starts_with((string) $key, $prefix)) {
                $filtered[$key] = $value;
            }
        }

        return $filtered;
    }

    /** @param array<string, mixed> $data */
    private function printArray(array $data, int $indent = 0): void
    {
        foreach ($data as $key => $value) {
            $prefix = str_repeat('  ', $indent);
            if (is_array($value)) {
                echo "{$prefix}\033[33m{$key}\033[0m:\n";
                $this->printArray($value, $indent + 1);
            } else {
                $valStr = is_bool($value) ? ($value ? 'true' : 'false') : (string)$value;
                echo "{$prefix}\033[32m{$key}\033[0m: {$valStr}\n";
            }
        }
    }
}
