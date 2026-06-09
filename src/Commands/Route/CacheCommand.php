<?php

declare(strict_types=1);

namespace Anvyr\Loom\Commands\Route;

use Anvyr\Loom\Commands\Command;
use Anvyr\Loom\Core\Application;

class CacheCommand extends Command
{
    public function __construct(
        private readonly Application $app
    ) {
    }

    public function signature(): string
    {
        return 'route:cache';
    }

    public function description(): string
    {
        return 'Cache the route definitions for faster routing';
    }

    public static function category(): string
    {
        return 'Optimization';
    }

    public function handle(): int
    {
        $this->info('Caching routes...');

        $cacheFile = storage_path('cache/routes.php');

        $router = $this->app->make('router');

        if ($this->app->has('modules')) {
            $this->app->make('modules')->loadRoutes();
        }

        $this->app->registerDefaultRoutes($router);

        $routeDefinitions = $router->getRouteDefinitions();

        $cacheable = [];
        $skipped = 0;

        foreach ($routeDefinitions as $id => $route) {
            if (!is_array($route['handler'])) {
                $skipped++;
                continue;
            }
            $cacheable[$id] = $route;
        }

        if ($skipped > 0) {
            $this->error('Route cache failed: closure-based routes cannot be cached.');
            $this->line("  {$skipped} closure-based route(s) found.");
            $this->line("  Convert them to [Controller::class, 'method'] handlers and try again.");
            return 1;
        }

        if ($cacheable === []) {
            $this->warning('No routes found to cache.');
            return 1;
        }

        $cacheDir = dirname($cacheFile);
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0755, true);
        }

        $export = var_export($cacheable, true);
        $content = "<?php\n\ndeclare(strict_types=1);\n\nreturn {$export};\n";

        $tempFile = tempnam($cacheDir, 'routes-');
        if ($tempFile === false) {
            $this->error('Failed to create temporary route cache file.');
            return 1;
        }

        if (file_put_contents($tempFile, $content) === false) {
            @unlink($tempFile);
            $this->error('Failed to write route cache file.');
            return 1;
        }

        $backupFile = null;
        if (file_exists($cacheFile)) {
            $backupFile = $cacheFile . '.' . uniqid('backup-', true);
            if (!rename($cacheFile, $backupFile)) {
                @unlink($tempFile);
                $this->error('Failed to prepare existing route cache for replacement.');
                return 1;
            }
        }

        if (!rename($tempFile, $cacheFile)) {
            @unlink($tempFile);

            if ($backupFile !== null && file_exists($backupFile)) {
                @rename($backupFile, $cacheFile);
            }

            $this->error('Failed to replace route cache file.');
            return 1;
        }

        if ($backupFile !== null && file_exists($backupFile)) {
            @unlink($backupFile);
        }

        clearstatcache(true, $cacheFile);
        if (function_exists('opcache_invalidate')) {
            @opcache_invalidate($cacheFile, true);
        }

        $count = count($cacheable);
        $this->success("Cached {$count} route definition(s).");
        $this->line("  Cache file: {$cacheFile}");

        return 0;
    }
}
