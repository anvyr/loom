<?php

declare(strict_types=1);

namespace Anvyr\Loom\Tests\Unit\Core;

use Anvyr\Loom\Core\ModuleManifest;
use Anvyr\Loom\Tests\Support\TestCase;

final class ModuleManifestSerializationTest extends TestCase
{
    public function test_to_array_includes_all_fields(): void
    {
        $manifest = new ModuleManifest(
            name: 'my-module',
            version: '1.2.3',
            path: '/modules/my-module',
            entry: 'MyModule\\Module',
            enabled: true,
            requires: ['core' => '^1.0'],
            conflicts: ['old-module'],
            provides: ['feature' => '1.0'],
            commands: ['mod:test' => 'MyModule\\Commands\\TestCommand'],
            routes: ['web' => 'routes/web.php'],
            views: 'resources/views',
            description: 'Description',
            stability: 'beta',
            extra: ['custom' => 'data'],
        );

        $array = $manifest->toArray();

        $this->assertSame('my-module', $array['name']);
        $this->assertSame('1.2.3', $array['version']);
        $this->assertSame('/modules/my-module', $array['path']);
        $this->assertSame('MyModule\\Module', $array['entry']);
        $this->assertTrue($array['enabled']);
        $this->assertSame(['core' => '^1.0'], $array['requires']);
        $this->assertSame(['old-module'], $array['conflicts']);
        $this->assertSame(['feature' => '1.0'], $array['provides']);
        $this->assertSame(['mod:test' => 'MyModule\\Commands\\TestCommand'], $array['commands']);
        $this->assertSame(['web' => 'routes/web.php'], $array['routes']);
        $this->assertSame('resources/views', $array['views']);
        $this->assertSame('Description', $array['description']);
        $this->assertSame('beta', $array['stability']);
        $this->assertSame(['custom' => 'data'], $array['extra']);
    }

    public function test_to_array_round_trips_through_from_array(): void
    {
        $original = ModuleManifest::fromArray('test-module', [
            'version' => '1.0.0',
            'path' => '/modules/test',
            'entry' => 'Test\\Module',
            'requires' => ['core' => '^1.0'],
            'description' => 'Test',
        ]);

        $array = $original->toArray();
        $restored = ModuleManifest::fromArray('test-module', $array, $original->enabled);

        $this->assertSame($original->name, $restored->name);
        $this->assertSame($original->version, $restored->version);
        $this->assertSame($original->path, $restored->path);
        $this->assertSame($original->entry, $restored->entry);
        $this->assertSame($original->requires, $restored->requires);
        $this->assertSame($original->description, $restored->description);
    }
}
