<?php

declare(strict_types=1);

namespace Anvyr\Loom\Tests\Unit\Core;

use Anvyr\Loom\Core\ModuleManifest;
use Anvyr\Loom\Tests\Support\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class ModuleManifestFactoryTest extends TestCase
{
    public function test_from_array_creates_manifest(): void
    {
        $manifest = ModuleManifest::fromArray('test-module', [
            'version' => '2.0.0',
            'path' => '/modules/test',
            'entry' => 'Test\\TestModule',
            'description' => 'Test description',
        ]);

        $this->assertSame('test-module', $manifest->name);
        $this->assertSame('2.0.0', $manifest->version);
        $this->assertSame('/modules/test', $manifest->path);
        $this->assertSame('Test\\TestModule', $manifest->entry);
        $this->assertSame('Test description', $manifest->description);
        $this->assertTrue($manifest->enabled);
    }

    public function test_from_array_uses_name_from_data_if_provided(): void
    {
        $manifest = ModuleManifest::fromArray('fallback-name', [
            'name' => 'actual-name',
            'path' => '/path',
            'entry' => 'Entry\\Class',
        ]);

        $this->assertSame('actual-name', $manifest->name);
    }

    public function test_from_array_defaults_version_to_zero(): void
    {
        $manifest = ModuleManifest::fromArray('test', [
            'path' => '/path',
            'entry' => 'Entry\\Class',
        ]);

        $this->assertSame('0.0.0', $manifest->version);
    }

    public function test_from_array_respects_enabled_parameter(): void
    {
        $enabled = ModuleManifest::fromArray('test', [
            'path' => '/path',
            'entry' => 'Entry\\Class',
        ], enabled: true);

        $disabled = ModuleManifest::fromArray('test', [
            'path' => '/path',
            'entry' => 'Entry\\Class',
        ], enabled: false);

        $this->assertTrue($enabled->enabled);
        $this->assertFalse($disabled->enabled);
    }

    public function test_from_array_normalizes_requires(): void
    {
        $manifest = ModuleManifest::fromArray('test', [
            'path' => '/path',
            'entry' => 'Entry\\Class',
            'requires' => [
                'valid-dep' => '^1.0',
                '' => 'ignored',
                'numeric-constraint' => 123,
            ],
        ]);

        $this->assertSame([
            'valid-dep' => '^1.0',
            'numeric-constraint' => '123',
        ], $manifest->requires);
    }

    public function test_from_array_normalizes_conflicts(): void
    {
        $manifest = ModuleManifest::fromArray('test', [
            'path' => '/path',
            'entry' => 'Entry\\Class',
            'conflicts' => ['module-a', '', 'module-b', null, 'module-c'],
        ]);

        $this->assertSame(['module-a', 'module-b', 'module-c'], $manifest->conflicts);
    }

    public function test_from_array_captures_extra_fields(): void
    {
        $manifest = ModuleManifest::fromArray('test', [
            'path' => '/path',
            'entry' => 'Entry\\Class',
            'extra' => [
                'custom_field' => 'custom_value',
                'another_extra' => ['nested' => 'data'],
            ],
        ]);

        $this->assertSame([
            'custom_field' => 'custom_value',
            'another_extra' => ['nested' => 'data'],
        ], $manifest->extra);
    }

    public function test_from_array_parses_commands(): void
    {
        $manifest = ModuleManifest::fromArray('test', [
            'path' => '/path',
            'entry' => 'Entry\\Class',
            'commands' => [
                'mod:action' => 'Module\\Commands\\ActionCommand',
                'mod:other' => 'Module\\Commands\\OtherCommand',
            ],
        ]);

        $this->assertSame([
            'mod:action' => 'Module\\Commands\\ActionCommand',
            'mod:other' => 'Module\\Commands\\OtherCommand',
        ], $manifest->commands);
    }

    public function test_from_array_parses_routes_and_views(): void
    {
        $manifest = ModuleManifest::fromArray('test', [
            'path' => '/path',
            'entry' => 'Entry\\Class',
            'routes' => [
                'web' => 'routes/site.php',
                'api' => 'routes/rest.php',
                'invalid' => 'ignored.php',
            ],
            'views' => 'resources/custom-views',
        ]);

        $this->assertSame([
            'web' => 'routes/site.php',
            'api' => 'routes/rest.php',
        ], $manifest->routes);
        $this->assertSame('resources/custom-views', $manifest->views);
    }

    public function test_from_array_normalizes_commands(): void
    {
        $manifest = ModuleManifest::fromArray('test', [
            'path' => '/path',
            'entry' => 'Entry\\Class',
            'commands' => [
                'valid:cmd' => 'Valid\\Command',
                '' => 'EmptyKey\\Command',
                'empty-class' => '',
            ],
        ]);

        $this->assertSame(['valid:cmd' => 'Valid\\Command'], $manifest->commands);
    }

    public function test_commands_are_not_captured_as_extra(): void
    {
        $manifest = ModuleManifest::fromArray('test', [
            'path' => '/path',
            'entry' => 'Entry\\Class',
            'commands' => ['cmd:test' => 'Test\\Command'],
        ]);

        $this->assertSame([], $manifest->extra);
    }

    #[DataProvider('provideNonArrayCollections')]
    public function test_from_array_handles_non_array_collections(string $field, string $property): void
    {
        $manifest = ModuleManifest::fromArray('test', [
            'path' => '/path',
            'entry' => 'Entry\\Class',
            $field => 'not-an-array',
        ]);

        $this->assertSame([], $manifest->{$property});
    }

    public static function provideNonArrayCollections(): array
    {
        return [
            'requires' => ['requires', 'requires'],
            'conflicts' => ['conflicts', 'conflicts'],
            'commands' => ['commands', 'commands'],
        ];
    }
}
