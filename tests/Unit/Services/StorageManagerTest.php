<?php

declare(strict_types=1);

namespace Anvyr\Loom\Tests\Unit\Services;

use Anvyr\Loom\Drivers\Storage\LocalDriver;
use Anvyr\Loom\Services\StorageManager;
use Anvyr\Loom\Tests\Support\TestCase;
use InvalidArgumentException;

final class StorageManagerTest extends TestCase
{
    private function makeConfig(): array
    {
        return [
            'default' => 'local',
            'disks' => [
                'local' => [
                    'driver' => 'local',
                    'root' => $this->tmpDir . '/storage',
                ],
            ],
        ];
    }

    public function test_resolves_default_disk(): void
    {
        $manager = new StorageManager($this->makeConfig());

        $disk = $manager->disk();

        $this->assertInstanceOf(LocalDriver::class, $disk);
    }

    public function test_dynamic_calls_forward_to_default_disk(): void
    {
        $manager = new StorageManager($this->makeConfig());

        $this->assertTrue($manager->put('test.txt', 'hello'));
        $this->assertSame('hello', $manager->get('test.txt'));
    }

    public function test_unconfigured_disk_throws(): void
    {
        $manager = new StorageManager($this->makeConfig());

        $this->expectException(InvalidArgumentException::class);
        $manager->disk('missing');
    }

    public function test_unsupported_driver_throws(): void
    {
        $config = $this->makeConfig();
        $config['disks']['remote'] = [
            'driver' => 's3',
            'root' => '/tmp',
        ];

        $manager = new StorageManager($config);

        $this->expectException(InvalidArgumentException::class);
        $manager->disk('remote');
    }
}
