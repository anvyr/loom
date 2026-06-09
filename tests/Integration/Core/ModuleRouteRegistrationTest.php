<?php

declare(strict_types=1);

namespace Anvyr\Loom\Tests\Integration\Core;

use Anvyr\Loom\Commands\Route\CacheCommand;
use Anvyr\Loom\Tests\Support\ModuleManagerTestCase;

final class ModuleRouteRegistrationTest extends ModuleManagerTestCase
{
    private string $routeCacheFile;

    protected function setUp(): void
    {
        parent::setUp();

        $this->routeCacheFile = storage_path('cache/routes.php');
        if (file_exists($this->routeCacheFile)) {
            unlink($this->routeCacheFile);
        }
    }

    public function test_load_routes_registers_explicit_route_registrars(): void
    {
        $modulePath = $this->createRouteModule();

        $this->seedCompiledRouteModule($modulePath);

        $this->moduleManager->load()->register();
        $this->moduleManager->loadRoutes();

        $router = $this->app->make('router');

        $webResponse = $router->dispatch($this->makeRequest('GET', '/route-module'));
        $apiResponse = $router->dispatch($this->makeRequest('GET', '/api/route-module'));

        $this->assertSame('module-web', $webResponse->getContent());
        $this->assertSame('module-api', $apiResponse->getContent());
    }

    public function test_route_cache_includes_routes_registered_by_explicit_registrars(): void
    {
        $modulePath = $this->createRouteModule();

        $this->seedCompiledRouteModule($modulePath);

        $command = new CacheCommand($this->app);

        [$exitCode, $output] = $this->captureOutput(fn () => $command->handle());

        $this->assertSame(0, $exitCode, $output);
        $this->assertFileExists($this->routeCacheFile);

        $cachedRoutes = require $this->routeCacheFile;
        $handlersByPath = [];

        foreach ($cachedRoutes as $route) {
            $handlersByPath[$route['path']] = $route['handler'];
        }

        $this->assertSame(['RouteModule\\RouteController', 'web'], $handlersByPath['/route-module'] ?? null);
        $this->assertSame(['RouteModule\\RouteController', 'api'], $handlersByPath['/api/route-module'] ?? null);
    }

    public function test_load_routes_rejects_scope_dependent_route_files(): void
    {
        $modulePath = $this->createManagerModule(
            'legacy-routes',
            'LegacyRoutes',
            [
                'version' => '1.0.0',
                'entry' => 'LegacyRoutes\\Module',
                'routes' => ['web' => 'routes/web.php'],
            ],
        );

        $this->writeFile($modulePath . '/routes/web.php', <<<'PHP'
<?php

declare(strict_types=1);

$router->get('/legacy', [LegacyRoutes\RouteController::class, 'web']);
PHP);

        $this->writeFile($modulePath . '/src/RouteController.php', <<<'PHP'
<?php

declare(strict_types=1);

namespace LegacyRoutes;

use Anvyr\Loom\Http\Request;
use Anvyr\Loom\Http\Response;

final class RouteController
{
    public function web(Request $request): Response
    {
        return Response::html('legacy');
    }
}
PHP);

        $this->writeCompiledModules([
            [
                'name' => 'legacy-routes',
                'version' => '1.0.0',
                'entry' => 'LegacyRoutes\\Module',
                'path' => $modulePath,
                'enabled' => true,
                'routes' => ['web' => 'routes/web.php'],
            ],
        ]);
        $this->writeAutoloadMap([
            'LegacyRoutes\\' => $modulePath . '/src',
        ]);

        $this->moduleManager->load()->register();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Route files must return static function (Router $router, Application $app): void');

        $this->moduleManager->loadRoutes();
    }

    private function createRouteModule(): string
    {
        $modulePath = $this->createManagerModule(
            'route-module',
            'RouteModule',
            [
                'version' => '1.0.0',
                'entry' => 'RouteModule\\Module',
                'routes' => [
                    'web' => 'routes/web.php',
                    'api' => 'routes/api.php',
                ],
            ],
        );

        $this->writeFile($modulePath . '/src/RouteController.php', <<<'PHP'
<?php

declare(strict_types=1);

namespace RouteModule;

use Anvyr\Loom\Http\Request;
use Anvyr\Loom\Http\Response;

final class RouteController
{
    public function web(Request $request): Response
    {
        return Response::html('module-web');
    }

    public function api(Request $request): Response
    {
        return Response::html('module-api');
    }
}
PHP);

        $this->writeFile($modulePath . '/routes/web.php', <<<'PHP'
<?php

declare(strict_types=1);

use RouteModule\RouteController;
use Anvyr\Loom\Core\Application;
use Anvyr\Loom\Http\Routing\Router;

return static function (Router $router, Application $app): void {
    $router->get('/route-module', [RouteController::class, 'web']);
};
PHP);

        $this->writeFile($modulePath . '/routes/api.php', <<<'PHP'
<?php

declare(strict_types=1);

use RouteModule\RouteController;
use Anvyr\Loom\Core\Application;
use Anvyr\Loom\Http\Routing\Router;

return static function (Router $router, Application $app): void {
    $router->get('/route-module', [RouteController::class, 'api']);
};
PHP);

        return $modulePath;
    }

    private function seedCompiledRouteModule(string $modulePath): void
    {
        $this->writeCompiledModules([
            [
                'name' => 'route-module',
                'version' => '1.0.0',
                'entry' => 'RouteModule\\Module',
                'path' => $modulePath,
                'enabled' => true,
                'routes' => [
                    'web' => 'routes/web.php',
                    'api' => 'routes/api.php',
                ],
            ],
        ]);
        $this->writeAutoloadMap([
            'RouteModule\\' => $modulePath . '/src',
        ]);
    }
}
