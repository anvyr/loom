<?php

declare(strict_types=1);

namespace Anvyr\Loom\Commands;

use Anvyr\Loom\Contracts\CacheDriver;
use Anvyr\Loom\Contracts\ContentDriver;
use Anvyr\Loom\Core\Application;
use Anvyr\Loom\Core\ModuleManager;
use Anvyr\Loom\Database\Connection;
use Throwable;

/** @phpstan-type CheckResult array{ok: bool, message: string} */
class DiagnoseCommand extends Command
{
    public static function category(): string
    {
        return 'System';
    }

    public function __construct(
        protected readonly Application $app,
        private readonly CacheDriver $cache,
        private readonly ModuleManager $modules,
        private readonly ContentDriver $contentDriver,
        private readonly Connection $connection
    ) {
    }

    public function signature(): string
    {
        return 'diagnose [--json]';
    }

    public function description(): string
    {
        return 'Run environment diagnostics and report system health';
    }

    public function handle(): int
    {
        $report = [
            'cache' => $this->checkCache(),
            'storage' => $this->checkStorage(),
            'database' => $this->checkDatabase(),
            'content_driver' => $this->inspectContentDriver(),
            'modules' => $this->inspectModules(),
        ];

        $hasFailure = array_reduce($report, static function (bool $carry, array $result): bool {
            return $carry || !$result['ok'];
        }, false);

        if ($this->option('json', false)) {
            $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}');
        } else {
            $rows = [];
            foreach ($report as $section => $data) {
                $rows[] = [
                    $section,
                    $data['ok'] ? 'OK' : 'FAIL',
                    $data['message'],
                ];
            }

            $this->table(['Check', 'Status', 'Details'], $rows);
        }

        return $hasFailure ? 1 : 0;
    }

    /** @return CheckResult */
    private function checkCache(): array
    {
        $key = 'diagnose:' . bin2hex(random_bytes(8));
        $value = bin2hex(random_bytes(8));

        try {
            $this->cache->set($key, $value, 5);
            $retrieved = $this->cache->get($key);
            $this->cache->delete($key);

            if ($retrieved === $value) {
                return ['ok' => true, 'message' => 'Cache driver operational'];
            }

            return ['ok' => false, 'message' => 'Cache read/write mismatch'];
        } catch (Throwable $e) {
            return ['ok' => false, 'message' => 'Cache error: ' . $e->getMessage()];
        }
    }

    /** @return CheckResult */
    private function checkStorage(): array
    {
        $paths = [
            storage_path(),
            storage_path('cache'),
            storage_path('logs'),
        ];

        foreach ($paths as $path) {
            if (!is_dir($path)) {
                return ['ok' => false, 'message' => "Missing directory: {$path}"];
            }

            if (!is_writable($path)) {
                return ['ok' => false, 'message' => "Directory not writable: {$path}"];
            }
        }

        return ['ok' => true, 'message' => 'Storage directories present and writable'];
    }

    /** @return CheckResult */
    private function checkDatabase(): array
    {
        try {
            $pdo = $this->connection->getPdo();
            $pdo->query('SELECT 1');
            return ['ok' => true, 'message' => 'Database connection established'];
        } catch (Throwable $e) {
            return ['ok' => false, 'message' => 'Database error: ' . $e->getMessage()];
        }
    }

    /** @return CheckResult */
    private function inspectContentDriver(): array
    {
        try {
            $count = $this->contentDriver->count();
            return ['ok' => true, 'message' => sprintf('File-based content (%d pages)', $count)];
        } catch (Throwable $e) {
            return ['ok' => false, 'message' => 'Content driver error: ' . $e->getMessage()];
        }
    }

    /** @return CheckResult */
    private function inspectModules(): array
    {
        try {
            $modules = $this->modules->all();
            return ['ok' => true, 'message' => sprintf('Loaded modules: %d', count($modules))];
        } catch (Throwable $e) {
            return ['ok' => false, 'message' => 'Module manager error: ' . $e->getMessage()];
        }
    }
}
