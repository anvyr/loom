<?php

declare(strict_types=1);

namespace Anvyr\Loom\Commands;

abstract class Command
{
    public const SUCCESS = 0;
    public const FAILURE = 1;

    /** @var array<int|string, mixed> */
    protected array $arguments = [];

    /** @var array<string, mixed> */
    protected array $options = [];

    abstract public function handle(): int;

    abstract public function signature(): string;

    abstract public function description(): string;

    public static function category(): string
    {
        return 'General';
    }

    /** @param array<int|string, mixed> $arguments */
    public function setArguments(array $arguments): void
    {
        $this->arguments = $arguments;
    }

    /** @param array<string, mixed> $options */
    public function setOptions(array $options): void
    {
        $this->options = $options;
    }

    protected function argument(string|int $key, mixed $default = null): mixed
    {
        return $this->arguments[$key] ?? $default;
    }

    protected function option(string $key, mixed $default = null): mixed
    {
        return $this->options[$key] ?? $default;
    }

    protected function info(string $message): void
    {
        echo "\033[36m[INFO]\033[0m {$message}\n";
    }

    protected function success(string $message): void
    {
        echo "\033[32m[✓]\033[0m {$message}\n";
    }

    protected function error(string $message): void
    {
        echo "\033[31m[✗]\033[0m {$message}\n";
    }

    protected function warning(string $message): void
    {
        echo "\033[33m[!]\033[0m {$message}\n";
    }

    protected function line(string $message = ''): void
    {
        echo $message . "\n";
    }

    protected function ask(string $question, ?string $default = null): string
    {
        $prompt = $default ? "{$question} [{$default}]: " : "{$question}: ";
        echo $prompt;

        $handle = fopen('php://stdin', 'r');
        if ($handle === false) {
            return $default ?? '';
        }

        $line = fgets($handle);
        fclose($handle);

        $answer = is_string($line) ? trim($line) : '';

        return $answer ?: ($default ?? '');
    }

    protected function confirm(string $question, bool $default = false): bool
    {
        $defaultText = $default ? 'Y/n' : 'y/N';
        $answer = strtolower($this->ask("{$question} [{$defaultText}]"));

        if ($answer === '') {
            return $default;
        }

        return in_array($answer, ['y', 'yes', 'true', '1']);
    }

    protected function secret(string $question): string
    {
        echo "{$question}: ";

        system('stty -echo');
        $handle = fopen('php://stdin', 'r');
        $line = $handle !== false ? fgets($handle) : false;
        if ($handle !== false) {
            fclose($handle);
        }
        system('stty echo');

        echo "\n";

        return is_string($line) ? trim($line) : '';
    }

    /** @param array<array-key, mixed> $choices */
    protected function choice(string $question, array $choices, mixed $default = null): mixed
    {
        $this->line($question);

        foreach ($choices as $key => $choice) {
            $this->line("  [{$key}] {$choice}");
        }

        $answer = $this->ask('Select option', $default !== null ? (string) $default : null);

        return $choices[$answer] ?? $default;
    }

    /**
     * @param list<string> $headers
     * @param list<list<mixed>> $rows
     */
    protected function table(array $headers, array $rows): void
    {
        $columnWidths = array_map('strlen', $headers);

        foreach ($rows as $row) {
            foreach ($row as $index => $cell) {
                $columnWidths[$index] = max($columnWidths[$index], strlen((string) $cell));
            }
        }

        $separator = '+';
        foreach ($columnWidths as $width) {
            $separator .= str_repeat('-', $width + 2) . '+';
        }

        $this->line($separator);

        $headerRow = '|';
        foreach ($headers as $index => $header) {
            $headerRow .= ' ' . str_pad($header, $columnWidths[$index]) . ' |';
        }
        $this->line($headerRow);
        $this->line($separator);

        foreach ($rows as $row) {
            $rowStr = '|';
            foreach ($row as $index => $cell) {
                $rowStr .= ' ' . str_pad((string) $cell, $columnWidths[$index]) . ' |';
            }
            $this->line($rowStr);
        }

        $this->line($separator);
    }

    protected function progressBar(int $total): callable
    {
        $current = 0;

        return function () use ($total, &$current) {
            $current++;
            $percent = round(($current / $total) * 100);
            $bar = str_repeat('=', (int) ($percent / 2)) . str_repeat(' ', 50 - (int) ($percent / 2));
            echo "\r[{$bar}] {$percent}%";

            if ($current >= $total) {
                echo "\n";
            }
        };
    }
}
