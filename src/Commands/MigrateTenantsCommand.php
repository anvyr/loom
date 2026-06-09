<?php

declare(strict_types=1);

namespace Anvyr\Loom\Commands;

use Anvyr\Loom\Commands\Concerns\InteractsWithTenancy;
use Anvyr\Loom\Core\Application;

class MigrateTenantsCommand extends Command
{
    use InteractsWithTenancy;

    public static function category(): string
    {
        return 'Database';
    }

    public function __construct(
        private readonly Application $app
    ) {
    }

    public function signature(): string
    {
        return 'migrate:tenants [--tenant=] [--all-tenants] [--batch=25] [--retry=1] [--from=] [--path=] [--force] [--fresh-checkpoint]';
    }

    public function description(): string
    {
        return 'Run database migrations for all tenants with batching, retry, and resume checkpoint';
    }

    public function handle(): int
    {
        try {
            $tenantIds = $this->resolveTenantSelection(allowAllTenants: true, fallbackToCurrentTenant: false);
        } catch (\RuntimeException $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }

        if ($tenantIds === []) {
            $this->warning('No tenants discovered.');
            return self::SUCCESS;
        }

        $batchSize = max(1, (int) $this->option('batch', 25));
        $maxRetries = max(0, (int) $this->option('retry', 1));
        $fromTenant = $this->option('from');
        $path = $this->option('path');
        $force = (bool) $this->option('force', false);

        $checkpointFile = $this->checkpointFile();

        if ((bool) $this->option('fresh-checkpoint', false) && file_exists($checkpointFile)) {
            unlink($checkpointFile);
        }

        $checkpoint = $this->loadCheckpoint($checkpointFile);

        if (is_string($fromTenant) && $fromTenant !== '') {
            $index = array_search($fromTenant, $tenantIds, true);
            if ($index === false) {
                $this->error("Tenant '{$fromTenant}' not found.");
                return self::FAILURE;
            }
            $tenantIds = array_slice($tenantIds, $index);
        } else {
            $completed = array_flip($checkpoint['completed']);
            $tenantIds = array_values(array_filter($tenantIds, static fn (string $id): bool => !isset($completed[$id])));
        }

        if ($tenantIds === []) {
            $this->success('No pending tenants to migrate (checkpoint is up to date).');
            return self::SUCCESS;
        }

        $this->info('Starting tenant migration orchestration...');
        $this->line('  Tenants pending: ' . count($tenantIds));
        $this->line("  Batch size: {$batchSize}");
        $this->line("  Retries: {$maxRetries}");

        $failures = [];

        $chunks = array_chunk($tenantIds, $batchSize);
        foreach ($chunks as $batchIndex => $batch) {
            $this->line();
            $this->line("\033[1mBatch " . ($batchIndex + 1) . '/' . count($chunks) . "\033[0m");

            foreach ($batch as $tenantId) {
                $ok = $this->runTenantMigration($tenantId, $force, $path, $maxRetries);

                if ($ok) {
                    $checkpoint['completed'][] = $tenantId;
                    $checkpoint['completed'] = array_values(array_unique($checkpoint['completed']));
                    unset($checkpoint['failed'][$tenantId]);
                } else {
                    $failures[] = $tenantId;
                    $checkpoint['failed'][$tenantId] = date('c');
                }

                $checkpoint['updated_at'] = date('c');
                $this->storeCheckpoint($checkpointFile, $checkpoint);
            }
        }

        if ($failures !== []) {
            $this->error('Tenant migrations finished with failures: ' . implode(', ', array_values(array_unique($failures))));
            $this->line('Resume with same command to skip completed tenants from checkpoint.');
            return self::FAILURE;
        }

        $this->success('All tenant migrations completed successfully.');
        return self::SUCCESS;
    }

    private function runTenantMigration(string $tenantId, bool $force, mixed $path, int $maxRetries): bool
    {
        $this->line("  - tenant: {$tenantId}");

        for ($attempt = 0; $attempt <= $maxRetries; $attempt++) {
            $command = 'migrate';
            if ($force) {
                $command .= ' --force';
            }
            if (is_string($path) && $path !== '') {
                $command .= ' --path=' . escapeshellarg($path);
            }

            $exitCode = $this->runLoomSubcommand($this->app->basePath(), $command, $tenantId);

            if ($exitCode === 0) {
                $this->line('    \033[32mOK\033[0m');
                return true;
            }

            if ($attempt < $maxRetries) {
                $retryNumber = $attempt + 1;
                $this->warning("    retrying ({$retryNumber}/{$maxRetries})...");
            }
        }

        $this->line('    \033[31mFAILED\033[0m');
        return false;
    }

    private function checkpointFile(): string
    {
        return base_path('storage/tenancy/migrate-tenants-checkpoint.json');
    }

    /** @return array{completed: list<string>, failed: array<string, mixed>, created_at: string, updated_at: string} */
    private function loadCheckpoint(string $file): array
    {
        if (!file_exists($file)) {
            return [
                'completed' => [],
                'failed' => [],
                'created_at' => date('c'),
                'updated_at' => date('c'),
            ];
        }

        $decoded = json_decode((string) file_get_contents($file), true);
        if (!is_array($decoded)) {
            return [
                'completed' => [],
                'failed' => [],
                'created_at' => date('c'),
                'updated_at' => date('c'),
            ];
        }

        return [
            'completed' => array_values(array_unique(array_filter((array) ($decoded['completed'] ?? []), 'is_string'))),
            'failed' => (array) ($decoded['failed'] ?? []),
            'created_at' => is_string($decoded['created_at'] ?? null) ? $decoded['created_at'] : date('c'),
            'updated_at' => is_string($decoded['updated_at'] ?? null) ? $decoded['updated_at'] : date('c'),
        ];
    }

    /** @param array{completed: list<string>, failed: array<string, mixed>, created_at: string, updated_at: string} $checkpoint */
    private function storeCheckpoint(string $file, array $checkpoint): void
    {
        $dir = dirname($file);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents($file, json_encode($checkpoint, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }
}
