<?php

declare(strict_types=1);

namespace Anvyr\Loom\Tests\Unit\Commands\Module;

use Anvyr\Loom\Commands\Module\InfoModuleCommand;
use Anvyr\Loom\Core\Application;
use Anvyr\Loom\Core\ModuleManifest;
use Anvyr\Loom\Core\Tenancy\ModuleArtifactPaths;
use Anvyr\Loom\Tests\Support\Concerns\CreatesModuleFixtures;
use Anvyr\Loom\Tests\Support\TestCase;

final class InfoModuleCommandTest extends TestCase
{
    use CreatesModuleFixtures;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mkdir($this->tmpDir . '/storage');
    }

    public function test_handle_reports_declared_routes_and_views_from_manifest(): void
    {
        $modulePath = $this->createModuleFixture(
            'info-module',
            'InfoModule',
            [
                'routes' => [
                    'web' => 'routes/site.php',
                    'api' => 'routes/rest.php',
                ],
                'views' => 'resources/custom-views',
                'description' => 'Info fixture',
            ]
        );

        rename($modulePath . '/routes/web.php', $modulePath . '/routes/site.php');
        rename($modulePath . '/routes/api.php', $modulePath . '/routes/rest.php');
        $this->mkdir($modulePath . '/resources/custom-views');
        rename($modulePath . '/resources/views/index.velvet.php', $modulePath . '/resources/custom-views/index.velvet.php');
        $artifactPaths = app(ModuleArtifactPaths::class);

        file_put_contents(
            $artifactPaths->compiledPath(basePath: $this->tmpDir),
            json_encode([
                'timestamp' => '2026-04-05T00:00:00+00:00',
                'version' => '2.2.0',
                'modules' => [
                    array_merge(
                        (new ModuleManifest(
                            name: 'info-module',
                            version: '1.0.0',
                            path: $modulePath,
                            entry: 'InfoModule\\Module',
                            enabled: true,
                            routes: [
                                'web' => 'routes/site.php',
                                'api' => 'routes/rest.php',
                            ],
                            views: 'resources/custom-views',
                            description: 'Info fixture'
                        ))->toArray(),
                        ['load_order' => 1]
                    ),
                ],
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );

        file_put_contents(
            $artifactPaths->autoloadPath(basePath: $this->tmpDir),
            "<?php\n\nreturn " . var_export([
                'psr-4' => [
                    'InfoModule\\' => $modulePath . '/src',
                ],
                'files' => [],
            ], true) . ";\n"
        );

        $app = new Application($this->tmpDir);
        $command = new InfoModuleCommand($app);
        $command->setArguments(['info-module']);

        [$exitCode, $output] = $this->captureOutput(fn () => $command->handle());

        $this->assertSame(0, $exitCode, $output);
        $this->assertStringContainsString('resources/custom-views', $output);
        $this->assertStringContainsString('1 template(s)', $output);
        $this->assertStringContainsString('web: routes/site.php', $output);
        $this->assertStringContainsString('api: routes/rest.php', $output);
        $this->assertStringNotContainsString('Auto-load:', $output);
        $this->assertStringNotContainsString('resources/views', $output);
    }
}
