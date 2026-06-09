<?php

declare(strict_types=1);

namespace Anvyr\Loom\Tests\Support\Concerns;

trait CreatesModuleFixtures
{
    use WritesTestFiles;

    private const ROUTE_FILE_STUB = <<<'PHP'
<?php

declare(strict_types=1);

use Anvyr\Loom\Core\Application;
use Anvyr\Loom\Http\Routing\Router;

return static function (Router $router, Application $app): void {
};
PHP;

    protected function createModuleFixture(
        string $name,
        string $namespace,
        array $manifestOverrides = [],
        ?string $moduleSource = null,
        ?string $modulesRoot = null,
    ): string {
        $modulesRoot ??= $this->tmpDir . '/modules';
        $modulePath = rtrim($modulesRoot, '/\\') . '/' . $name;

        $this->mkdir($modulePath . '/src');
        $this->mkdir($modulePath . '/config');
        $this->mkdir($modulePath . '/resources/views');
        $this->mkdir($modulePath . '/routes');

        $manifest = array_merge([
            'name' => $name,
            'version' => '1.0.0',
            'entry' => $namespace . '\\Module',
            'description' => 'Fixture module',
        ], $manifestOverrides);

        $this->writeJsonFile($modulePath . '/module.json', $manifest);
        $this->writeJsonFile($modulePath . '/composer.json', [
            'name' => 'loom-modules/' . strtolower($name),
            'type' => 'loom-module',
            'autoload' => [
                'psr-4' => [
                    $namespace . '\\' => 'src/',
                ],
            ],
        ]);

        $this->writeFile($modulePath . '/src/Module.php', $moduleSource ?? <<<PHP
<?php

declare(strict_types=1);

namespace {$namespace};

use Anvyr\Loom\Core\BaseModule;

final class Module extends BaseModule
{
}
PHP);

        $this->writeFile($modulePath . '/routes/web.php', self::ROUTE_FILE_STUB);
        $this->writeFile($modulePath . '/routes/api.php', self::ROUTE_FILE_STUB);
        $this->writeFile($modulePath . '/resources/views/index.velvet.php', '<div>fixture</div>');

        return $modulePath;
    }
}
