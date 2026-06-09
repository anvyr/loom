<?php

declare(strict_types=1);

namespace Anvyr\Loom\Commands;

use Anvyr\Loom\Core\ModuleManager;
use Anvyr\Loom\Database\Migrations\Migrator;
use Throwable;

class MigrateCommand extends Command
{
    public static function category(): string
    {
        return 'Database';
    }

    public function __construct(
        private readonly Migrator $migrator,
        private readonly ?ModuleManager $moduleManager = null,
    ) {
    }

    public function signature(): string
    {
        return 'migrate [--force] [--path=]';
    }

    public function description(): string
    {
        return 'Run database migrations';
    }

    public function handle(): int
    {
        $defaultPath = base_path('database/migrations');
        $env = env('APP_ENV', 'production');

        if ($env === 'production' && !$this->option('force')) {
            $this->warning('Running migrations in production!');

            if (!$this->confirm('Are you sure?', false)) {
                $this->info('Migration cancelled');
                return 0;
            }
        }

        $paths = $this->resolvePaths($defaultPath);

        if ($this->option('path') === null) {
            $paths = array_merge($paths, $this->getModuleMigrationPaths());
        }

        $pathsWithMigrations = array_filter($paths, function (string $path): bool {
            return (glob($path . '/*') ?: []) !== [];
        });

        if ($pathsWithMigrations === []) {
            $this->warning('No migration files found');
            return 0;
        }

        try {
            foreach ($paths as $path) {
                $this->info("Running migrations in {$path}...");
                $this->migrator->run($path);
                $this->line('');
            }

            $this->success('All migrations completed successfully!');
            return 0;
        } catch (Throwable $e) {
            $this->error("Migration failed: {$e->getMessage()}");
            return 1;
        }
    }

    /**
     * @return string[]
     */
    private function resolvePaths(string $defaultPath): array
    {
        $option = $this->option('path');

        if ($option === null) {
            return [$defaultPath];
        }

        $raw = is_array($option) ? $option : explode(',', (string) $option);

        $paths = [];

        foreach ($raw as $value) {
            $value = trim((string) $value);

            if ($value === '') {
                continue;
            }

            $resolved = str_starts_with($value, DIRECTORY_SEPARATOR)
                ? $value
                : base_path($value);

            $paths[] = rtrim($resolved, '/\\');
        }

        return $paths === [] ? [$defaultPath] : array_values(array_unique($paths));
    }

    /**
     * @return string[]
     */
    private function getModuleMigrationPaths(): array
    {
        if ($this->moduleManager === null) {
            return [];
        }

        $paths = [];

        foreach ($this->moduleManager->all() as $module) {
            if (method_exists($module, 'getMigrationPaths')) {
                $paths = array_merge($paths, $module->getMigrationPaths());
                continue;
            }

            $path = $module->path('database/migrations');
            if (is_dir($path)) {
                $paths[] = $path;
            }
        }

        return $paths;
    }
}
