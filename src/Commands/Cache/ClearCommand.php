<?php

declare(strict_types=1);

namespace Anvyr\Loom\Commands\Cache;

use Anvyr\Loom\Commands\Command;

class ClearCommand extends Command
{
    public static function category(): string
    {
        return 'Cache';
    }

    public function signature(): string
    {
        return 'cache:clear';
    }

    public function description(): string
    {
        return 'Clear all cached data';
    }

    public function handle(): int
    {
        $this->info('Clearing application cache...');

        $cachePath = storage_path('cache');
        $cleared = 0;

        if (!is_dir($cachePath)) {
            $this->warning('Cache directory not found');
            return 0;
        }

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($cachePath, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($files as $fileinfo) {
            $path = $fileinfo->getRealPath();

            if ($fileinfo->isDir()) {
                rmdir($path);
            } else {
                unlink($path);
                $cleared++;
            }
        }

        $this->success("Cache cleared! ({$cleared} files removed)");

        return 0;
    }
}
