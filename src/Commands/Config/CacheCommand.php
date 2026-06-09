<?php

declare(strict_types=1);

namespace Anvyr\Loom\Commands\Config;

use Anvyr\Loom\Commands\Command;
use Anvyr\Loom\Core\ConfigRepository;

class CacheCommand extends Command
{
    public static function category(): string
    {
        return 'Config';
    }

    public function signature(): string
    {
        return 'config:cache';
    }

    public function description(): string
    {
        return 'Compile configuration files into a single cache file.';
    }

    public function handle(): int
    {
        $this->info('Caching configuration...');

        /** @var ConfigRepository $repository */
        $repository = app(ConfigRepository::class);
        $destination = storage_path('cache/config.php');

        $repository->cacheTo($destination);

        $this->success('Configuration cached successfully.');
        $this->line("  Path: {$destination}");

        return self::SUCCESS;
    }
}
