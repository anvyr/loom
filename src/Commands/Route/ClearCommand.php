<?php

declare(strict_types=1);

namespace Anvyr\Loom\Commands\Route;

use Anvyr\Loom\Commands\Command;

class ClearCommand extends Command
{
    public function signature(): string
    {
        return 'route:clear';
    }

    public function description(): string
    {
        return 'Clear the cached route definitions';
    }

    public static function category(): string
    {
        return 'Optimization';
    }

    public function handle(): int
    {
        $cacheFile = storage_path('cache/routes.php');

        if (!file_exists($cacheFile)) {
            $this->info('Route cache does not exist.');
            return 0;
        }

        if (unlink($cacheFile)) {
            $this->success('Route cache cleared successfully.');
            return 0;
        }

        $this->error('Failed to clear route cache.');
        return 1;
    }
}
