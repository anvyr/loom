<?php

declare(strict_types=1);

namespace Anvyr\Loom\Tests\Unit\Core;

use Anvyr\Loom\Core\ModuleManifest;
use Anvyr\Loom\Tests\Support\TestCase;
use InvalidArgumentException;

final class ModuleManifestConstructionTest extends TestCase
{
    public function test_constructor_requires_name(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('name must not be empty');

        new ModuleManifest(
            name: '',
            version: '1.0.0',
            path: '/path/to/module',
            entry: 'MyModule\\Module',
            enabled: true,
        );
    }

    public function test_constructor_requires_path(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('path must not be empty');

        new ModuleManifest(
            name: 'my-module',
            version: '1.0.0',
            path: '',
            entry: 'MyModule\\Module',
            enabled: true,
        );
    }

    public function test_constructor_requires_entry(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('entry must not be empty');

        new ModuleManifest(
            name: 'my-module',
            version: '1.0.0',
            path: '/path/to/module',
            entry: '',
            enabled: true,
        );
    }

    public function test_constructor_accepts_valid_manifest(): void
    {
        $manifest = new ModuleManifest(
            name: 'my-module',
            version: '1.0.0',
            path: '/modules/my-module',
            entry: 'MyModule\\Module',
            enabled: true,
            requires: ['core' => '^1.0'],
            conflicts: ['old-module'],
            provides: ['feature' => '1.0'],
            commands: ['cmd:test' => 'MyModule\\Commands\\TestCommand'],
            routes: ['web' => 'routes/web.php'],
            views: 'resources/views',
            description: 'A test module',
            stability: 'stable',
            extra: ['custom' => 'value'],
        );

        $this->assertSame('my-module', $manifest->name);
        $this->assertSame('1.0.0', $manifest->version);
        $this->assertSame('/modules/my-module', $manifest->path);
        $this->assertSame('MyModule\\Module', $manifest->entry);
        $this->assertTrue($manifest->enabled);
        $this->assertSame(['core' => '^1.0'], $manifest->requires);
        $this->assertSame(['old-module'], $manifest->conflicts);
        $this->assertSame(['feature' => '1.0'], $manifest->provides);
        $this->assertSame(['cmd:test' => 'MyModule\\Commands\\TestCommand'], $manifest->commands);
        $this->assertSame(['web' => 'routes/web.php'], $manifest->routes);
        $this->assertSame('resources/views', $manifest->views);
        $this->assertSame('A test module', $manifest->description);
        $this->assertSame('stable', $manifest->stability);
        $this->assertSame(['custom' => 'value'], $manifest->extra);
    }

    public function test_nullable_fields_can_be_null(): void
    {
        $manifest = new ModuleManifest(
            name: 'test',
            version: '1.0.0',
            path: '/path',
            entry: 'Entry\\Class',
            enabled: true,
            description: null,
            stability: null,
        );

        $this->assertNull($manifest->description);
        $this->assertNull($manifest->stability);
    }

    public function test_empty_arrays_are_valid(): void
    {
        $manifest = new ModuleManifest(
            name: 'test',
            version: '1.0.0',
            path: '/path',
            entry: 'Entry\\Class',
            enabled: true,
            requires: [],
            conflicts: [],
            provides: [],
            commands: [],
            extra: [],
        );

        $this->assertSame([], $manifest->requires);
        $this->assertSame([], $manifest->conflicts);
        $this->assertSame([], $manifest->provides);
        $this->assertSame([], $manifest->commands);
        $this->assertSame([], $manifest->extra);
    }
}
