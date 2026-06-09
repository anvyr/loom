<?php

declare(strict_types=1);

namespace Anvyr\Loom\Services;

use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

class FileLogger implements LoggerInterface
{
    private string $basePath;
    private string $level;
    private bool $daily;
    private int $maxFiles;
    private ?string $rotationCheckedDate = null;

    private const LEVELS = [
        LogLevel::DEBUG => 0,
        LogLevel::INFO => 1,
        LogLevel::NOTICE => 2,
        LogLevel::WARNING => 3,
        LogLevel::ERROR => 4,
        LogLevel::CRITICAL => 5,
        LogLevel::ALERT => 6,
        LogLevel::EMERGENCY => 7,
    ];

    public function __construct(
        ?string $logPath = null,
        string $level = LogLevel::INFO,
        bool $daily = false,
        int $maxFiles = 7
    ) {
        $this->basePath = $logPath ?? storage_path('logs/loom.log');
        $this->level = $level;
        $this->daily = $daily;
        $this->maxFiles = $maxFiles;

        $dir = dirname($this->basePath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }

    public function emergency(string|\Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::EMERGENCY, $message, $context);
    }

    public function alert(string|\Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::ALERT, $message, $context);
    }

    public function critical(string|\Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::CRITICAL, $message, $context);
    }

    public function error(string|\Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::ERROR, $message, $context);
    }

    public function warning(string|\Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::WARNING, $message, $context);
    }

    public function notice(string|\Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::NOTICE, $message, $context);
    }

    public function info(string|\Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::INFO, $message, $context);
    }

    public function debug(string|\Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::DEBUG, $message, $context);
    }

    public function log($level, string|\Stringable $message, array $context = []): void
    {
        if (!$this->shouldLog($level)) {
            return;
        }

        $timestamp = date('Y-m-d H:i:s');
        $levelUpper = strtoupper($level);

        $message = $this->interpolate((string) $message, $context);

        // Format: [2025-11-12 14:30:45] ERROR: Message here
        $line = "[{$timestamp}] {$levelUpper}: {$message}";

        if (isset($context['exception']) && $context['exception'] instanceof \Throwable) {
            $line .= "\n" . $this->formatException($context['exception']);
        }

        $line .= "\n";

        $logPath = $this->getLogPath();
        file_put_contents($logPath, $line, FILE_APPEND | LOCK_EX);

        if ($this->daily) {
            $this->rotateOldFiles();
        }
    }

    public function getLogPath(): string
    {
        if (!$this->daily) {
            return $this->basePath;
        }

        $info = pathinfo($this->basePath);
        $date = date('Y-m-d');
        $dirname = $info['dirname'] ?? '.';

        return $dirname . '/' . $info['filename'] . '-' . $date . '.' . ($info['extension'] ?? 'log');
    }

    private function rotateOldFiles(): void
    {
        $today = date('Y-m-d');

        // Only check once per day per instance
        if ($this->rotationCheckedDate === $today) {
            return;
        }
        $this->rotationCheckedDate = $today;

        $info = pathinfo($this->basePath);
        $dirname = $info['dirname'] ?? '.';
        $pattern = $dirname . '/' . $info['filename'] . '-*.'. ($info['extension'] ?? 'log');

        $files = glob($pattern);
        if ($files === false || count($files) <= $this->maxFiles) {
            return;
        }

        // Sort by modification time, oldest first
        usort($files, fn ($a, $b) => filemtime($a) <=> filemtime($b));

        // Delete oldest files
        $toDelete = array_slice($files, 0, count($files) - $this->maxFiles);
        foreach ($toDelete as $file) {
            @unlink($file);
        }
    }

    private function shouldLog(string $level): bool
    {
        $messageLevel = self::LEVELS[$level] ?? 0;
        $configuredLevel = self::LEVELS[$this->level] ?? 0;

        return $messageLevel >= $configuredLevel;
    }

    /** @param array<string, mixed> $context */
    private function interpolate(string $message, array $context): string
    {
        $replace = [];

        foreach ($context as $key => $val) {
            if ($key === 'exception') {
                continue;
            }

            // Convert value to string
            if (is_scalar($val) || (is_object($val) && method_exists($val, '__toString'))) {
                $replace['{' . $key . '}'] = (string) $val;
            } elseif (is_array($val)) {
                $replace['{' . $key . '}'] = json_encode($val);
            } elseif (is_object($val)) {
                $replace['{' . $key . '}'] = get_class($val);
            }
        }

        return strtr($message, $replace);
    }

    private function formatException(\Throwable $e): string
    {
        $trace = $e->getTraceAsString();
        return sprintf(
            "Exception: %s\nMessage: %s\nFile: %s:%d\nTrace:\n%s",
            get_class($e),
            $e->getMessage(),
            $e->getFile(),
            $e->getLine(),
            $trace
        );
    }
}
