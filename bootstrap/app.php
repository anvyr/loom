<?php

declare(strict_types=1);

/**
 * Shared application bootstrap for HTTP, CLI, and tests.
 */

use Anvyr\Loom\Content\Index\JsonPageIndex;
use Anvyr\Loom\Content\Index\PageIndex;
use Anvyr\Loom\Content\Index\PageIndexer;
use Anvyr\Loom\Content\Index\SqlitePageIndex;
use Anvyr\Loom\Contracts\CacheDriver;
use Anvyr\Loom\Contracts\ContentDriver;
use Anvyr\Loom\Contracts\DataStore;
use Anvyr\Loom\Core\Application;
use Anvyr\Loom\Core\EventDispatcher;
use Anvyr\Loom\Core\Paths;
use Anvyr\Loom\Database\Connection;
use Anvyr\Loom\Drivers\Content\FileDriver;
use Anvyr\Loom\Drivers\Data\AutoDataStore;
use Anvyr\Loom\Services\ContentParser;
use Anvyr\Loom\Services\PageService;

$basePath = Paths::fromBootstrapEnvironment()->base();

if (!defined('LOOM_BASE_PATH')) {
    define('LOOM_BASE_PATH', $basePath);
}

$app = new Application($basePath);

// Generic data store for modules (auto-switches between file and database)
$app->singleton('data', function () use ($app) {
    // Try to get database connection, but don't fail if unavailable
    $connection = null;
    try {
        $connection = $app->make(Connection::class);
    } catch (\Throwable) {
        // Database not configured or unavailable
    }

    return new AutoDataStore($connection, storage_path('data'));
});
$app->alias('data', DataStore::class);
$app->alias('data', AutoDataStore::class);

$app->singleton(PageIndexer::class, function () {
    return new PageIndexer();
});

$app->singleton(PageIndex::class, function () use ($app) {
    $driver = (string) config('content.drivers.file.index.driver', 'json');

    return match ($driver) {
        'sqlite' => new SqlitePageIndex(
            $app->make(Connection::class),
        ),
        default => new JsonPageIndex(
            (string) config('content.drivers.file.index.json.path', storage_path('index/page-index.json')),
        ),
    };
});

// File-native page content
$app->singleton('content.driver', function () use ($app) {
    return new FileDriver(
        $app->make(ContentParser::class),
        $app->make(PageIndex::class),
        $app->make(PageIndexer::class),
        config('content.drivers.file.path', content_path('pages')),
    );
});
$app->alias('content.driver', ContentDriver::class);

// Page service orchestrator
$app->singleton('pages', function () use ($app) {
    return new PageService(
        $app->make(ContentDriver::class),
        $app->make(PageIndex::class),
        $app->make(EventDispatcher::class),
        $app->make(CacheDriver::class),
        $app->make(\Anvyr\Loom\Support\Cache\CacheTagManager::class),
        $app->make(\Anvyr\Loom\Core\ConfigRepository::class)
    );
});
$app->alias('pages', PageService::class);

return $app;
