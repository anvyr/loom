<?php

declare(strict_types=1);

namespace Anvyr\Loom\Commands;

use Anvyr\Loom\Core\Application;

class CommandRegistry
{
    /** @var array<string, array{class: class-string<Command>, category: string|null, hidden: bool}> */
    private array $commands = [];

    private ?Application $app = null;

    public function setApp(Application $app): void
    {
        $this->app = $app;
    }

    /** @param array{category?: string|null, hidden?: bool} $options */
    public function register(string $name, string $commandClass, array $options = []): void
    {
        $this->assertCommandClass($commandClass);

        $this->commands[$name] = [
            'class' => $commandClass,
            'category' => $options['category'] ?? null,
            'hidden' => (bool) ($options['hidden'] ?? false),
        ];
    }

    /** @phpstan-assert class-string<Command> $commandClass */
    private function assertCommandClass(string $commandClass): void
    {
        if (!is_subclass_of($commandClass, Command::class)) {
            throw new \InvalidArgumentException(
                'Command class must extend ' . Command::class
            );
        }
    }

    public function has(string $name): bool
    {
        return isset($this->commands[$name]);
    }

    /**
     * @return array{class: class-string<Command>, category: string|null, hidden: bool}|null
     */
    public function get(string $name): ?array
    {
        return $this->commands[$name] ?? null;
    }

    /**
     * @return array<string, array{class: class-string<Command>, category: string|null, hidden: bool}>
     */
    public function all(): array
    {
        return $this->commands;
    }

    /**
     * @return array<string, array<string, array{class: class-string<Command>, category: string|null, hidden: bool}>>
     */
    public function grouped(): array
    {
        $groups = [];

        foreach ($this->commands as $name => $meta) {
            if (!empty($meta['hidden'])) {
                continue;
            }

            $class = $meta['class'];
            $category = $meta['category'] ?? $class::category();

            $groups[$category][$name] = $meta;
        }

        ksort($groups);

        foreach ($groups as &$commands) {
            ksort($commands);
        }

        return $groups;
    }

    /**
     * @param array<int|string, mixed> $arguments
     * @param array<string, mixed> $options
     */
    public function run(string $name, array $arguments = [], array $options = []): int
    {
        $commandMeta = $this->get($name);

        if ($commandMeta === null) {
            echo "\033[31mCommand '{$name}' not found.\033[0m\n";
            echo "Run 'loom list' to see available commands.\n";
            return 1;
        }

        $commandClass = $commandMeta['class'];
        $command = $this->resolveCommand($commandClass);

        $command->setArguments($arguments);
        $command->setOptions($options);

        try {
            return $command->handle();
        } catch (\Throwable $e) {
            echo "\033[31m[ERROR]\033[0m {$e->getMessage()}\n";

            if (config('app.debug', false)) {
                echo "\n{$e->getTraceAsString()}\n";
            }

            return 1;
        }
    }

    /** @param class-string<Command> $commandClass */
    private function resolveCommand(string $commandClass): Command
    {
        if ($this->app !== null) {
            try {
                $command = $this->app->make($commandClass);
                if ($command instanceof Command) {
                    return $command;
                }
            } catch (\Throwable) {
                return $this->makeCommand($commandClass);
            }
        }

        return $this->makeCommand($commandClass);
    }

    /** @param class-string<Command> $commandClass */
    private function makeCommand(string $commandClass): Command
    {
        $reflection = new \ReflectionClass($commandClass);
        $constructor = $reflection->getConstructor();

        if (!$constructor) {
            return new $commandClass();
        }

        $dependencies = [];
        foreach ($constructor->getParameters() as $param) {
            $type = $param->getType();

            if ($type instanceof \ReflectionNamedType && !$type->isBuiltin()) {
                $typeName = $type->getName();

                if ($typeName === self::class) {
                    $dependencies[] = $this;
                } elseif ($this->app !== null) {
                    try {
                        $dependencies[] = $this->app->make($typeName);
                    } catch (\Throwable) {
                        $dependencies[] = $param->isDefaultValueAvailable() ? $param->getDefaultValue() : null;
                    }
                } elseif ($param->isDefaultValueAvailable()) {
                    $dependencies[] = $param->getDefaultValue();
                }
            } elseif ($param->isDefaultValueAvailable()) {
                $dependencies[] = $param->getDefaultValue();
            }
        }

        return new $commandClass(...$dependencies);
    }

    /**
     * @param list<string> $argv
     * @return array{0: ?string, 1: list<string>, 2: array<string, string|true>}
     */
    public function parseArgv(array $argv): array
    {
        array_shift($argv);

        $commandName = $argv[0] ?? null;
        $arguments = [];
        $options = [];

        if (!$commandName || str_starts_with($commandName, '-')) {
            return [null, $arguments, $options];
        }

        array_shift($argv);

        foreach ($argv as $arg) {
            if (str_starts_with($arg, '--')) {
                $parts = explode('=', substr($arg, 2), 2);
                $options[$parts[0]] = $parts[1] ?? true;
            } elseif (str_starts_with($arg, '-')) {
                $flag = substr($arg, 1);
                $options[$flag] = true;
            } else {
                $arguments[] = $arg;
            }
        }

        return [$commandName, $arguments, $options];
    }
}
