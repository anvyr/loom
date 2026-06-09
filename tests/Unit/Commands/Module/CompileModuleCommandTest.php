<?php

declare(strict_types=1);

namespace Anvyr\Loom\Tests\Unit\Commands\Module;

use Anvyr\Loom\Commands\Module\CompileModuleCommand;
use Anvyr\Loom\Core\Application;
use Anvyr\Loom\Core\Tenancy\ModuleArtifactPaths;
use Anvyr\Loom\Tests\Support\Concerns\CreatesModuleFixtures;
use Anvyr\Loom\Tests\Support\TestCase;

final class CompileModuleCommandTest extends TestCase
{
    use CreatesModuleFixtures;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mkdir($this->tmpDir . '/modules');
        $this->mkdir($this->tmpDir . '/storage');
    }

    public function test_handle_preserves_declared_routes_and_views_in_compiled_manifest(): void
    {
        $modulePath = $this->createModuleFixture(
            'blog-module',
            'BlogModule',
            [
                'routes' => [
                    'web' => 'routes/site.php',
                    'api' => 'routes/rest.php',
                ],
                'views' => 'resources/custom-views',
                'extra' => ['feature_flag' => true],
            ]
        );
        $artifactPaths = app(ModuleArtifactPaths::class);

        file_put_contents(
            $artifactPaths->statePath(basePath: $this->tmpDir),
            json_encode(['enabled' => ['blog-module']], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );

        $app = new Application($this->tmpDir);
        config([
            'modules.paths' => [$this->tmpDir . '/modules/*'],
            'modules.modules' => [],
            'modules.auto_discover' => true,
        ]);
        $command = new CompileModuleCommand($app);

        [$exitCode, $output] = $this->captureOutput(fn () => $command->handle());

        $this->assertSame(0, $exitCode, $output);

        $compiled = json_decode(
            (string) file_get_contents($artifactPaths->compiledPath(basePath: $this->tmpDir)),
            true,
            flags: JSON_THROW_ON_ERROR
        );

        $this->assertSame($modulePath, $compiled['modules'][0]['path']);
        $this->assertSame([
            'web' => 'routes/site.php',
            'api' => 'routes/rest.php',
        ], $compiled['modules'][0]['routes']);
        $this->assertSame('resources/custom-views', $compiled['modules'][0]['views']);
        $this->assertSame(['feature_flag' => true], $compiled['modules'][0]['extra']);
        $this->assertSame(1, $compiled['modules'][0]['load_order']);
    }

}
