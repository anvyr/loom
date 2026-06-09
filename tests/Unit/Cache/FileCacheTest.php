<?php

declare(strict_types=1);

namespace Anvyr\Loom\Tests\Unit\Cache;

use Anvyr\Loom\Models\Page;
use Anvyr\Loom\Tests\Support\TestCase;

final class FileCacheTest extends TestCase
{
    private string $cachePath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cachePath = $this->tmpDir . '/cache';
        if (!is_dir($this->cachePath)) {
            mkdir($this->cachePath, 0755, true);
        }
    }

    public function test_it_can_store_and_restore_objects(): void
    {
        $cache = $this->makeFileCache('test_');

        $page = new Page('cached', 'Cached Page', 'Content');

        $cache->set('page:cached', $page, 300);

        $cached = $cache->get('page:cached');

        $this->assertInstanceOf(Page::class, $cached);
        $this->assertSame('Cached Page', $cached->title);
    }

    public function test_clear_removes_nested_directories(): void
    {
        $cache = $this->makeFileCache('test_');

        $cache->set('foo', 'bar', 300);

        $hashDir = glob($this->cachePath . '/*');
        $this->assertNotEmpty($hashDir, 'Expected hashed directories to be created');

        $cache->clear();

        $remaining = array_diff(scandir($this->cachePath) ?: [], ['.', '..']);
        $this->assertSame([], $remaining, 'Cache directory should be empty after clear');
    }
}
