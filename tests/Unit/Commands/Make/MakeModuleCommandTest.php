<?php

declare(strict_types=1);

namespace Anvyr\Loom\Tests\Unit\Commands\Make;

use Anvyr\Loom\Commands\Make\MakeModuleCommand;
use Anvyr\Loom\Tests\Support\ApplicationTestCase;

final class MakeModuleCommandTest extends ApplicationTestCase
{
    public function test_handle_generates_declarative_module_manifest(): void
    {
        $moduleName = 'GeneratedModule' . bin2hex(random_bytes(4));
        $modulePath = base_path("user/modules/{$moduleName}");

        $command = new MakeModuleCommand();
        $command->setArguments([$moduleName]);

        [$exitCode, $output] = $this->captureOutput(fn () => $command->handle());

        $this->assertSame(0, $exitCode, $output);
        $this->assertFileExists($modulePath . '/module.json');
        $this->assertFileExists($modulePath . '/routes/web.php');
        $this->assertFileExists($modulePath . '/routes/api.php');

        $manifest = json_decode(
            (string) file_get_contents($modulePath . '/module.json'),
            true,
            flags: JSON_THROW_ON_ERROR
        );
        $composer = json_decode(
            (string) file_get_contents($modulePath . '/composer.json'),
            true,
            flags: JSON_THROW_ON_ERROR
        );

        $this->assertSame('resources/views', $manifest['views']);
        $this->assertSame([
            'web' => 'routes/web.php',
            'api' => 'routes/api.php',
        ], $manifest['routes']);
        $this->assertStringContainsString(
            'return static function (Router $router, Application $app): void',
            (string) file_get_contents($modulePath . '/routes/web.php')
        );
        $this->assertStringContainsString(
            'return static function (Router $router, Application $app): void',
            (string) file_get_contents($modulePath . '/routes/api.php')
        );
        $this->assertSame('>=2.2.0', $manifest['requires']['core']);
        $this->assertArrayNotHasKey('autoload', $manifest);
        $this->assertSame([
            'psr-4' => [
                'GeneratedModule' . substr($moduleName, strlen('GeneratedModule')) . '\\' => 'src/',
            ],
        ], $composer['autoload']);
    }
}
