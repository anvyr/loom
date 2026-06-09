<?php

declare(strict_types=1);

namespace Anvyr\Loom\Commands;

use ReflectionClass;
use Throwable;

class HelpCommand extends Command
{
    public static function category(): string
    {
        return 'General';
    }

    public function __construct(
        private readonly CommandRegistry $registry
    ) {
    }

    public function signature(): string
    {
        return 'help [command]';
    }

    public function description(): string
    {
        return 'Show details about a command or list commands';
    }

    public function handle(): int
    {
        $commandName = (string) $this->argument(0, '');

        if ($commandName === '') {
            $list = new ListCommand($this->registry);
            $list->setArguments($this->arguments);
            $list->setOptions($this->options);
            return $list->handle();
        }

        $meta = $this->registry->get($commandName);

        if ($meta === null) {
            $this->error("Command '{$commandName}' not found");
            $this->line("Run 'loom list' to see available commands");
            return 1;
        }

        $commandClass = $meta['class'];

        try {
            $reflection = new ReflectionClass($commandClass);
            $command = $reflection->newInstanceWithoutConstructor();
        } catch (Throwable $e) {
            $this->error('Unable to load command metadata');
            if (config('app.debug', false)) {
                $this->line($e->getMessage());
            }
            return 1;
        }

        $signature = trim($command->signature());
        $description = trim($command->description());
        $category = $meta['category'] ?? $commandClass::category();

        $this->line();
        $this->line("\033[1m{$commandName}\033[0m");
        $this->line(str_repeat('-', strlen($commandName)));
        $this->line("\033[33mDescription:\033[0m {$description}");
        $this->line("\033[33mCategory:\033[0m {$category}");
        $this->line();
        $this->line("\033[33mUsage:\033[0m");
        $this->line("  loom {$signature}");

        $arguments = $this->extractArguments($signature);
        $options = $this->extractOptions($signature);

        if ($arguments !== []) {
            $this->line();
            $this->line("\033[33mArguments:\033[0m");
            foreach ($arguments as $argument) {
                $this->line("  {$argument}");
            }
        }

        if ($options !== []) {
            $this->line();
            $this->line("\033[33mOptions:\033[0m");
            foreach ($options as $option) {
                $this->line("  {$option}");
            }
        }

        $this->line();

        return 0;
    }

    /**
     * @return string[]
     */
    private function extractArguments(string $signature): array
    {
        if ($signature === '') {
            return [];
        }

        $parts = preg_split('/\s+/', $signature) ?: [];
        $arguments = [];

        foreach ($parts as $part) {
            if (str_starts_with($part, '[') && str_contains($part, '--')) {
                continue;
            }

            if (str_contains($part, ':')) {
                continue;
            }

            if (str_starts_with($part, '[') || str_starts_with($part, '<')) {
                $arguments[] = trim($part, '[]<>');
            }
        }

        return $arguments;
    }

    /**
     * @return string[]
     */
    private function extractOptions(string $signature): array
    {
        if ($signature === '') {
            return [];
        }

        $parts = preg_split('/\s+/', $signature) ?: [];
        $options = [];

        foreach ($parts as $part) {
            if (!str_contains($part, '--')) {
                continue;
            }

            $clean = trim($part, '[]');
            $options[] = $clean;
        }

        return $options;
    }
}
