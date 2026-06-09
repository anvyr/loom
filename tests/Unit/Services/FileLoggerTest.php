<?php

declare(strict_types=1);

namespace Anvyr\Loom\Tests\Unit\Services;

use Anvyr\Loom\Services\FileLogger;
use Anvyr\Loom\Tests\Support\TestCase;
use Psr\Log\LogLevel;
use RuntimeException;

final class FileLoggerTest extends TestCase
{
    public function test_respects_log_level_threshold(): void
    {
        $logPath = $this->tmpDir . '/logs/level.log';
        $logger = new FileLogger($logPath, LogLevel::WARNING);

        $logger->info('Informational');
        $logger->error('Something failed');

        $contents = file_get_contents($logPath) ?: '';

        $this->assertStringNotContainsString('INFO: Informational', $contents);
        $this->assertStringContainsString('ERROR: Something failed', $contents);
    }

    public function test_interpolates_context_and_formats_exception(): void
    {
        $logPath = $this->tmpDir . '/logs/context.log';
        $logger = new FileLogger($logPath, LogLevel::DEBUG);

        $exception = new RuntimeException('Boom');
        $logger->error('User {id} failed', ['id' => 42, 'exception' => $exception]);

        $contents = file_get_contents($logPath) ?: '';

        $this->assertStringContainsString('ERROR: User 42 failed', $contents);
        $this->assertStringContainsString('Exception:', $contents);
        $this->assertStringContainsString('RuntimeException', $contents);
    }

    // === Daily Rotation ===

    public function test_daily_creates_dated_file(): void
    {
        $logPath = $this->tmpDir . '/logs/app.log';
        $logger = new FileLogger($logPath, LogLevel::DEBUG, daily: true);

        $logger->info('Test message');

        $expectedPath = $this->tmpDir . '/logs/app-' . date('Y-m-d') . '.log';
        $this->assertFileExists($expectedPath);

        $contents = file_get_contents($expectedPath) ?: '';
        $this->assertStringContainsString('Test message', $contents);
    }

    public function test_daily_false_uses_single_file(): void
    {
        $logPath = $this->tmpDir . '/logs/single.log';
        $logger = new FileLogger($logPath, LogLevel::DEBUG, daily: false);

        $logger->info('Test message');

        $this->assertFileExists($logPath);
        $this->assertStringContainsString('Test message', file_get_contents($logPath) ?: '');
    }

    public function test_get_log_path_returns_dated_path_when_daily(): void
    {
        $logPath = $this->tmpDir . '/logs/dated.log';
        $logger = new FileLogger($logPath, LogLevel::DEBUG, daily: true);

        $expected = $this->tmpDir . '/logs/dated-' . date('Y-m-d') . '.log';
        $this->assertSame($expected, $logger->getLogPath());
    }

    public function test_get_log_path_returns_base_path_when_not_daily(): void
    {
        $logPath = $this->tmpDir . '/logs/base.log';
        $logger = new FileLogger($logPath, LogLevel::DEBUG, daily: false);

        $this->assertSame($logPath, $logger->getLogPath());
    }

    public function test_rotation_removes_old_files(): void
    {
        // Use unique dir per test run to avoid static lastCheck issue
        $logDir = $this->tmpDir . '/logs-rotation-' . uniqid();
        mkdir($logDir, 0755, true);

        // Create old log files (more than maxFiles)
        for ($i = 1; $i <= 5; $i++) {
            $oldDate = date('Y-m-d', strtotime("-{$i} days"));
            $oldFile = "{$logDir}/rotate-{$oldDate}.log";
            file_put_contents($oldFile, "Old log {$i}");
            touch($oldFile, strtotime("-{$i} days")); // Set modification time
        }

        // 5 old files exist
        $this->assertCount(5, glob("{$logDir}/rotate-*.log"));

        $logPath = $logDir . '/rotate.log';
        $logger = new FileLogger($logPath, LogLevel::DEBUG, daily: true, maxFiles: 3);

        // This should trigger rotation
        $logger->info('New message');

        // maxFiles=3 means keep 3 files total (including today's)
        $files = glob("{$logDir}/rotate-*.log");
        $this->assertLessThanOrEqual(3, count($files));

        // Today's file should exist
        $todayFile = "{$logDir}/rotate-" . date('Y-m-d') . '.log';
        $this->assertFileExists($todayFile);
    }
}
