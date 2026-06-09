<?php

declare(strict_types=1);

namespace Anvyr\Loom\Tests\Unit\Commands;

use Anvyr\Loom\Commands\MigrateTenantsCommand;
use Anvyr\Loom\Core\Application;
use Anvyr\Loom\Tests\Support\Concerns\ReflectionHelpers;
use Anvyr\Loom\Tests\Support\Concerns\TenancyTestHelpers;
use Anvyr\Loom\Tests\Support\TestCase;

final class MigrateTenantsCommandTest extends TestCase
{
    use ReflectionHelpers;
    use TenancyTestHelpers;
    protected function tearDown(): void
    {
        $this->resetTenancyState();
        parent::tearDown();
    }

    public function test_load_checkpoint_returns_defaults_when_file_missing(): void
    {
        $command = $this->makeCommand();

        $result = $this->callPrivateMethod($command, 'loadCheckpoint', [$this->tmpDir . '/nonexistent.json']);

        $this->assertSame([], $result['completed']);
        $this->assertSame([], $result['failed']);
        $this->assertArrayHasKey('created_at', $result);
        $this->assertArrayHasKey('updated_at', $result);
    }

    public function test_load_checkpoint_returns_defaults_for_corrupt_json(): void
    {
        $file = $this->tmpDir . '/corrupt.json';
        file_put_contents($file, 'not-json');

        $command = $this->makeCommand();

        $result = $this->callPrivateMethod($command, 'loadCheckpoint', [$file]);

        $this->assertSame([], $result['completed']);
        $this->assertSame([], $result['failed']);
    }

    public function test_load_checkpoint_deduplicates_completed(): void
    {
        $file = $this->tmpDir . '/checkpoint.json';
        file_put_contents($file, json_encode([
            'completed' => ['alpha', 'beta', 'alpha'],
            'failed' => [],
            'created_at' => '2026-01-01T00:00:00+00:00',
            'updated_at' => '2026-01-01T00:00:00+00:00',
        ]));

        $command = $this->makeCommand();

        $result = $this->callPrivateMethod($command, 'loadCheckpoint', [$file]);

        $this->assertSame(['alpha', 'beta'], $result['completed']);
    }

    public function test_store_checkpoint_creates_directory_and_writes_file(): void
    {
        $file = $this->tmpDir . '/deep/nested/checkpoint.json';

        $checkpoint = [
            'completed' => ['alpha'],
            'failed' => ['beta' => '2026-02-24T00:00:00+00:00'],
            'created_at' => '2026-02-24T00:00:00+00:00',
            'updated_at' => '2026-02-24T00:00:00+00:00',
        ];

        $command = $this->makeCommand();

        $this->callPrivateMethod($command, 'storeCheckpoint', [$file, $checkpoint]);

        $this->assertFileExists($file);

        $decoded = json_decode(file_get_contents($file), true);
        $this->assertSame(['alpha'], $decoded['completed']);
        $this->assertSame('2026-02-24T00:00:00+00:00', $decoded['failed']['beta']);
    }

    public function test_store_and_load_roundtrip(): void
    {
        $file = $this->tmpDir . '/roundtrip.json';

        $checkpoint = [
            'completed' => ['a', 'b', 'c'],
            'failed' => ['d' => '2026-02-24T12:00:00+00:00'],
            'created_at' => '2026-02-24T00:00:00+00:00',
            'updated_at' => '2026-02-24T12:00:00+00:00',
        ];

        $command = $this->makeCommand();

        $this->callPrivateMethod($command, 'storeCheckpoint', [$file, $checkpoint]);
        $loaded = $this->callPrivateMethod($command, 'loadCheckpoint', [$file]);

        $this->assertSame(['a', 'b', 'c'], $loaded['completed']);
        $this->assertSame('2026-02-24T12:00:00+00:00', $loaded['failed']['d']);
    }

    public function test_handle_returns_success_when_no_tenants(): void
    {
        $this->setTenancyConfig([
            'enabled' => true,
            'paths' => ['user_root' => $this->tmpDir . '/empty-tenants'],
        ]);

        $command = $this->makeCommand();
        $command->setOptions([]);

        [$exitCode] = $this->captureOutput(fn () => $command->handle());

        $this->assertSame(0, $exitCode);
    }

    private function makeCommand(): MigrateTenantsCommand
    {
        $app = $this->createStub(Application::class);
        $app->method('basePath')->willReturn($this->tmpDir);

        return new MigrateTenantsCommand($app);
    }

}
