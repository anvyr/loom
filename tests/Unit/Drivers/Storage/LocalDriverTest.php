<?php

declare(strict_types=1);

namespace Anvyr\Loom\Tests\Unit\Drivers\Storage;

use Anvyr\Loom\Drivers\Storage\LocalDriver;
use Anvyr\Loom\Tests\Support\TestCase;
use RuntimeException;

final class LocalDriverTest extends TestCase
{
    private LocalDriver $driver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->driver = new LocalDriver([
            'root' => $this->tmpDir . '/storage',
        ]);
    }

    public function test_put_get_delete_roundtrip(): void
    {
        $this->assertTrue($this->driver->put('dir/file.txt', 'content'));
        $this->assertTrue($this->driver->exists('dir/file.txt'));
        $this->assertSame('content', $this->driver->get('dir/file.txt'));

        $this->assertTrue($this->driver->delete('dir/file.txt'));
        $this->assertFalse($this->driver->exists('dir/file.txt'));
    }

    public function test_files_lists_recursive_files(): void
    {
        $this->driver->put('a.txt', 'a');
        $this->driver->put('dir/b.txt', 'b');

        $files = $this->driver->files('', true);
        sort($files);

        $this->assertSame(['a.txt', 'dir/b.txt'], $files);
    }

    public function test_url_requires_public_url(): void
    {
        $this->expectException(RuntimeException::class);
        $this->driver->url('file.txt');
    }

    public function test_path_sanitizes_traversal_segments(): void
    {
        $this->driver->put('../evil.txt', 'x');

        $rootPath = $this->driver->path('');
        $insidePath = $rootPath . DIRECTORY_SEPARATOR . 'evil.txt';

        $this->assertTrue(file_exists($insidePath));

        $parentPath = dirname($rootPath) . DIRECTORY_SEPARATOR . 'evil.txt';
        $this->assertFalse(file_exists($parentPath));
    }
}
