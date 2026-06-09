<?php

declare(strict_types=1);

namespace Anvyr\Loom\Scheduling;

use Anvyr\Loom\Core\Application;

class Task
{
    protected string $expression = '* * * * *';
    protected mixed $callback;

    /** @var list<mixed> */
    protected array $parameters = [];
    protected ?string $command = null;
    protected string $description = '';

    /** @param list<mixed> $parameters */
    public function __construct(mixed $callback, array $parameters = [])
    {
        $this->callback = $callback;
        $this->parameters = $parameters;
    }

    /** @param list<string> $parameters */
    public static function command(string $command, array $parameters = []): self
    {
        $task = new self(null, $parameters);
        $task->command = $command;
        return $task;
    }

    public function run(Application $app): void
    {
        if ($this->command) {
            $binary = defined('PHP_BINARY') ? PHP_BINARY : 'php';
            $loom = $app->basePath() . '/loom';

            // Run in foreground
            $command = build_cli_command($binary, $loom, $this->command);
            passthru($command);
            return;
        }

        if (is_callable($this->callback)) {
            call_user_func_array($this->callback, $this->parameters);
        }
    }

    public function isDue(): bool
    {
        return $this->expressionPasses();
    }

    protected function expressionPasses(): bool
    {
        $date = new \DateTime();
        $cronParts = explode(' ', $this->expression);

        if (count($cronParts) !== 5) {
            return false;
        }

        list($min, $hour, $day, $month, $weekday) = $cronParts;

        return $this->isTimePartDue($min, (int)$date->format('i')) &&
               $this->isTimePartDue($hour, (int)$date->format('H')) &&
               $this->isTimePartDue($day, (int)$date->format('d')) &&
               $this->isTimePartDue($month, (int)$date->format('m')) &&
               $this->isTimePartDue($weekday, (int)$date->format('w'));
    }

    protected function isTimePartDue(string $expression, int $current): bool
    {
        if ($expression === '*') {
            return true;
        }

        if (str_contains($expression, ',')) {
            $parts = explode(',', $expression);
            return in_array((string)$current, $parts);
        }

        if (str_contains($expression, '/')) {
            list($base, $step) = explode('/', $expression);
            if ($base === '*') {
                $base = 0;
            }
            return ($current % (int)$step) === 0;
        }

        return (int)$expression === $current;
    }

    // Frequencies

    public function everyMinute(): self
    {
        $this->expression = '* * * * *';
        return $this;
    }

    public function hourly(): self
    {
        $this->expression = '0 * * * *';
        return $this;
    }

    public function daily(): self
    {
        $this->expression = '0 0 * * *';
        return $this;
    }

    public function dailyAt(int $hour, int $minute = 0): self
    {
        $this->expression = sprintf('%d %d * * *', $minute, $hour);
        return $this;
    }

    public function getCommand(): ?string
    {
        return $this->command;
    }

    /** @return list<mixed> */
    public function getParameters(): array
    {
        return $this->parameters;
    }
}
