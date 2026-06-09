<?php

declare(strict_types=1);

namespace Anvyr\Loom\Commands;

class ListCommand extends Command
{
    public static function category(): string
    {
        return 'General';
    }

    public function __construct(
        private readonly ?CommandRegistry $registry = null
    ) {
    }

    public function signature(): string
    {
        return 'list';
    }

    public function description(): string
    {
        return 'List all available commands';
    }

    public function handle(): int
    {
        if (!$this->registry) {
            $this->error('Command registry not available');
            return 1;
        }

        $registry = app(\Anvyr\Loom\Core\VersionRegistry::class);
        $coreVersion = $registry->getVersion('core');

        $this->line();
        $this->line("\033[1mAnvyr Loom\033[0m \033[33m{$coreVersion}\033[0m");

        $moduleEntries = $registry->getModules();
        if (!empty($moduleEntries)) {
            $modules = [];
            foreach ($moduleEntries as $name => $meta) {
                $moduleVersion = $meta['version'] ?? 'unknown';
                $stability = $meta['stability'] ?? null;
                $modules[] = $stability ? sprintf('%s@%s (%s)', $name, $moduleVersion, $stability) : sprintf('%s@%s', $name, $moduleVersion);
            }

            $this->line("\033[90mModules:\033[0m " . implode(', ', $modules));
        }

        $this->line();
        $this->line("\033[33mUsage:\033[0m");
        $this->line('  loom <command> [arguments] [options]');
        $this->line();
        $this->line("\033[33mAvailable Commands:\033[0m");

        $grouped = $this->registry->grouped();

        foreach ($grouped as $groupName => $commands) {
            if ($commands === []) {
                continue;
            }

            $this->line();
            $this->line("\033[32m{$groupName}\033[0m");

            foreach ($commands as $commandName => $meta) {
                $commandClass = $meta['class'];

                try {
                    if ($commandClass === self::class) {
                        $description = 'List all available commands';
                    } else {
                        $reflection = new \ReflectionClass($commandClass);
                        $instance = $reflection->newInstanceWithoutConstructor();
                        $description = $instance->description();
                    }

                    $signature = str_pad($commandName, 20);
                    $this->line("  \033[36m{$signature}\033[0m {$description}");
                } catch (\Throwable $e) {
                }
            }
        }

        $this->line();

        return 0;
    }
}
