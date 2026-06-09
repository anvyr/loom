<?php

declare(strict_types=1);

namespace Anvyr\Loom\Commands;

use Anvyr\Loom\Core\ConfigRepository;

class OptimizeCommand extends Command
{
    public static function category(): string
    {
        return 'Maintenance';
    }

    public function signature(): string
    {
        return 'optimize';
    }

    public function description(): string
    {
        return 'Optimize application for production';
    }

    public function handle(): int
    {
        $this->info('Optimizing Anvyr Loom for production...');
        $this->line();

        $this->info('[1/3] Clearing old cache...');
        $cachePath = storage_path('cache');

        if (is_dir($cachePath)) {
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
                }
            }

            $this->success('  ✓ Cache cleared');
        }

        $this->info('[2/3] Warming up configuration...');

        $configs = ['app', 'cache', 'content', 'db', 'modules', 'version', 'theme'];
        foreach ($configs as $config) {
            config($config);
        }

        /** @var ConfigRepository $repository */
        $repository = app(ConfigRepository::class);
        $repository->cacheTo(storage_path('cache/config.php'));

        $this->success('  ✓ Configuration cached');

        $this->info('[3/3] Checking environment...');

        $env = env('APP_ENV', 'production');
        $debug = env('APP_DEBUG', false);

        if ($env !== 'production') {
            $this->warning("  ⚠ APP_ENV is '{$env}', should be 'production'");
        } else {
            $this->success('  ✓ APP_ENV is production');
        }

        if ($debug) {
            $this->warning('  ⚠ APP_DEBUG is enabled, should be disabled in production');
        } else {
            $this->success('  ✓ APP_DEBUG is disabled');
        }

        $this->line();
        $this->success('Application optimized!');
        $this->line();
        $this->info('Production checklist:');
        $this->line('  • Set APP_ENV=production in your environment');
        $this->line('  • Set APP_DEBUG=false in your environment');
        $this->line('  • Use redis/memcached for CACHE_DRIVER');
        $this->line('  • Set proper file permissions (755 for dirs, 644 for files)');
        $this->line('  • Enable OPcache in php.ini');
        $this->line();

        return 0;
    }
}
