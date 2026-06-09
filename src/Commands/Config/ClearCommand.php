<?php

declare(strict_types=1);

namespace Anvyr\Loom\Commands\Config;

use Anvyr\Loom\Commands\Command;
use Anvyr\Loom\Core\ConfigRepository;

class ClearCommand extends Command
{
    public static function category(): string
    {
        return 'Config';
    }

    public function signature(): string
    {
        return 'config:clear';
    }

    public function description(): string
    {
        return 'Delete the cached configuration file.';
    }

    public function handle(): int
    {
        /** @var ConfigRepository $repository */
        $repository = app(ConfigRepository::class);
        $cacheFile = storage_path('cache/config.php');

        if (!file_exists($cacheFile)) {
            $this->line('No cached configuration found.');
            return self::SUCCESS;
        }

        $repository->clearCache();
        $this->success('Configuration cache cleared.');

        return self::SUCCESS;
    }
}
