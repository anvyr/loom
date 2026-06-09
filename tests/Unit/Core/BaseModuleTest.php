<?php

declare(strict_types=1);

namespace Anvyr\Loom\Tests\Unit\Core;

use Anvyr\Loom\Core\Application;
use Anvyr\Loom\Core\ModuleManifest;
use Anvyr\Loom\Services\ViewEngine;
use Anvyr\Loom\Tests\Support\Concerns\WritesTestFiles;
use Anvyr\Loom\Tests\Support\Doubles\Modules\InspectableModule;
use Anvyr\Loom\Tests\Support\TestCase;

final class BaseModuleTest extends TestCase
{
    use WritesTestFiles;

    private string $modulePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->modulePath = $this->tmpDir . '/modules/test-module';
        $this->mkdir($this->modulePath);
    }

    private function createModule(array $manifestData = []): InspectableModule
    {
        $data = array_merge([
            'name' => 'test-module',
            'version' => '1.0.0',
            'path' => $this->modulePath,
            'entry' => InspectableModule::class,
            'description' => 'Test module description',
        ], $manifestData);

        $manifest = ModuleManifest::fromArray($data['name'], $data);
        return new InspectableModule($this->modulePath, $manifest);
    }

    // === Basic Properties ===

    public function test_name_returns_manifest_name(): void
    {
        $module = $this->createModule(['name' => 'my-module']);
        $this->assertSame('my-module', $module->name());
    }

    public function test_version_returns_manifest_version(): void
    {
        $module = $this->createModule(['version' => '2.3.4']);
        $this->assertSame('2.3.4', $module->version());
    }

    public function test_description_returns_manifest_description(): void
    {
        $module = $this->createModule(['description' => 'A great module']);
        $this->assertSame('A great module', $module->description());
    }

    public function test_description_returns_empty_when_not_set(): void
    {
        $manifest = new ModuleManifest(
            name: 'test',
            version: '1.0.0',
            path: $this->modulePath,
            entry: InspectableModule::class,
            enabled: true,
            description: null,
        );
        $module = new InspectableModule($this->modulePath, $manifest);

        $this->assertSame('', $module->description());
    }

    // === Path Methods ===

    public function test_path_returns_base_path(): void
    {
        $module = $this->createModule();
        $this->assertSame($this->modulePath, $module->path());
    }

    public function test_path_joins_subpath(): void
    {
        $module = $this->createModule();
        $this->assertSame($this->modulePath . '/src/Controllers', $module->path('src/Controllers'));
    }

    public function test_path_normalizes_leading_slash(): void
    {
        $module = $this->createModule();
        $this->assertSame($this->modulePath . '/config', $module->path('/config'));
    }

    public function test_path_handles_trailing_slash_in_base(): void
    {
        $manifest = ModuleManifest::fromArray('test', [
            'path' => $this->modulePath . '/',  // With trailing slash
            'entry' => InspectableModule::class,
        ]);
        $module = new InspectableModule($this->modulePath . '/', $manifest);

        // Should not double slashes
        $this->assertStringNotContainsString('//', $module->path('src'));
    }

    // === Public Path ===

    public function test_public_path_returns_null_when_no_public_dir(): void
    {
        $module = $this->createModule();
        $this->assertNull($module->publicPath());
    }

    public function test_public_path_returns_path_when_dir_exists(): void
    {
        $publicDir = $this->modulePath . '/public';
        $this->mkdir($publicDir);

        $module = $this->createModule();
        $this->assertSame($publicDir, $module->publicPath());
    }

    // === Assets Prefix ===

    public function test_assets_prefix_returns_module_name(): void
    {
        $module = $this->createModule(['name' => 'blog-module']);
        $this->assertSame('blog-module', $module->assetsPrefix());
    }

    // === Migration Paths ===

    public function test_get_migration_paths_returns_empty_when_no_migrations_dir(): void
    {
        $module = $this->createModule();
        $this->assertSame([], $module->getMigrationPaths());
    }

    public function test_get_migration_paths_includes_default_dir(): void
    {
        $migrationsDir = $this->modulePath . '/database/migrations';
        $this->mkdir($migrationsDir);

        $module = $this->createModule();
        $this->assertContains($migrationsDir, $module->getMigrationPaths());
    }

    // === Manifest Access ===

    public function test_manifest_object_returns_manifest(): void
    {
        $module = $this->createModule();
        $manifest = $module->manifestObject();

        $this->assertInstanceOf(ModuleManifest::class, $manifest);
        $this->assertSame('test-module', $manifest->name);
    }

    public function test_manifest_config_returns_known_field(): void
    {
        $module = $this->createModule(['version' => '3.0.0']);
        $this->assertSame('3.0.0', $module->manifestConfig('version'));
    }

    public function test_manifest_config_returns_extra_field(): void
    {
        $module = $this->createModule([
            'extra' => ['custom_setting' => 'custom_value'],
        ]);

        $this->assertSame('custom_value', $module->manifestConfig('custom_setting'));
    }

    public function test_manifest_config_returns_default_when_missing(): void
    {
        $module = $this->createModule();
        $this->assertSame('fallback', $module->manifestConfig('nonexistent', 'fallback'));
    }

    public function test_manifest_config_returns_null_default(): void
    {
        $module = $this->createModule();
        $this->assertNull($module->manifestConfig('missing'));
    }

    // === Register and Boot ===

    public function test_register_is_called(): void
    {
        $module = $this->createModule();
        $app = new Application($this->tmpDir);

        $module->register($app);

        $this->assertTrue($module->registerCalled);
    }

    public function test_boot_is_called(): void
    {
        $module = $this->createModule();
        $app = new Application($this->tmpDir);

        $module->boot($app);

        $this->assertTrue($module->bootCalled);
    }

    // === Views Loading ===

    public function test_load_views_from_registers_namespace(): void
    {
        $viewsPath = $this->modulePath . '/resources/views';
        $this->writeFile($viewsPath . '/index.velvet.php', 'Module View');

        $app = new Application($this->tmpDir);
        $viewEngine = new ViewEngine(
            $this->tmpDir . '/views',
            $this->tmpDir . '/cache/views'
        );
        $app->instance('view', $viewEngine);
        Application::setInstance($app);

        try {
            $module = $this->createModule();
            $module->exposeLoadViewsFrom($viewsPath, 'testmod');

            $output = $viewEngine->render('testmod::index');
            $this->assertSame('Module View', $output);
        } finally {
            Application::clearInstance();
        }
    }

    public function test_load_views_from_handles_missing_dir(): void
    {
        $app = new Application($this->tmpDir);
        $viewEngine = new ViewEngine(
            $this->tmpDir . '/views',
            $this->tmpDir . '/cache/views'
        );
        $app->instance('view', $viewEngine);
        Application::setInstance($app);

        try {
            $module = $this->createModule();
            $module->exposeLoadViewsFrom('/nonexistent/views', 'test');

            $this->assertFalse($viewEngine->exists('test::index'));
        } finally {
            Application::clearInstance();
        }
    }

    public function test_load_migrations_from_registers_custom_path(): void
    {
        $module = $this->createModule();
        $customPath = $this->modulePath . '/custom-migrations';

        $module->exposeLoadMigrationsFrom($customPath);

        $this->assertContains($customPath, $module->getMigrationPaths());
    }
}
