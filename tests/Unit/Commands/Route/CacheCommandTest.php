<?php

declare(strict_types=1);

namespace Anvyr\Loom\Tests\Unit\Commands\Route;

use Anvyr\Loom\Commands\Route\CacheCommand;
use Anvyr\Loom\Http\Controllers\PageController;
use Anvyr\Loom\Http\Controllers\WebCronController;
use Anvyr\Loom\Http\Request;
use Anvyr\Loom\Tests\Support\ApplicationTestCase;

final class CacheCommandTest extends ApplicationTestCase
{
    private string $cacheFile;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cacheFile = storage_path('cache/routes.php');

        if (file_exists($this->cacheFile)) {
            unlink($this->cacheFile);
        }
    }

    public function test_handle_caches_core_routes_with_controller_handlers(): void
    {
        config(['app.cron_enabled' => true]);

        $app = $this->makeApplication(boot: true);

        $command = new CacheCommand($app);

        [$exitCode, $output] = $this->captureOutput(fn () => $command->handle());

        $this->assertSame(0, $exitCode, $output);
        $this->assertFileExists($this->cacheFile);

        $cachedRoutes = require $this->cacheFile;
        $handlersByPath = [];

        foreach ($cachedRoutes as $route) {
            $handlersByPath[$route['path']] = $route['handler'];
        }

        $this->assertIsArray($cachedRoutes);
        $this->assertSame([PageController::class, 'home'], $handlersByPath['/'] ?? null);
        $this->assertSame([PageController::class, 'show'], $handlersByPath['/{slug*}'] ?? null);
        $this->assertSame([WebCronController::class, 'handle'], $handlersByPath['/system/cron'] ?? null);
    }

    public function test_handle_fails_when_any_closure_route_is_present(): void
    {
        config(['app.cron_enabled' => false]);

        $app = $this->makeApplication();
        $router = $app->make('router');

        $router->get('/closure', function (Request $request) {
            return 'closure';
        });

        $command = new CacheCommand($app);

        [$exitCode, $output] = $this->captureOutput(fn () => $command->handle());

        $this->assertSame(1, $exitCode, $output);
        $this->assertStringContainsString('closure-based routes cannot be cached', $output);
        $this->assertFileDoesNotExist($this->cacheFile);
    }

    public function test_handle_preserves_existing_cache_when_rebuild_fails(): void
    {
        config(['app.cron_enabled' => false]);

        $cacheDir = dirname($this->cacheFile);
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0755, true);
        }

        $existingCache = "<?php\n\ndeclare(strict_types=1);\n\nreturn ['existing' => 'cache'];\n";
        file_put_contents($this->cacheFile, $existingCache);

        $app = $this->makeApplication();
        $router = $app->make('router');

        $router->get('/closure', function (Request $request) {
            return 'closure';
        });

        $command = new CacheCommand($app);

        [$exitCode, $output] = $this->captureOutput(fn () => $command->handle());

        $this->assertSame(1, $exitCode, $output);
        $this->assertStringContainsString('closure-based routes cannot be cached', $output);
        $this->assertSame($existingCache, file_get_contents($this->cacheFile));
    }
}
