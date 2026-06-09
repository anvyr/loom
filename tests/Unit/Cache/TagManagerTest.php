<?php

declare(strict_types=1);

namespace Anvyr\Loom\Tests\Unit\Cache;

use Anvyr\Loom\Drivers\Cache\FileCache;
use Anvyr\Loom\Support\Cache\CacheTagManager;
use Anvyr\Loom\Tests\Support\TestCase;

final class TagManagerTest extends TestCase
{
    private string $cachePath;
    private FileCache $cache;
    private CacheTagManager $tags;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cachePath = $this->tmpDir . '/cache_tags';
        mkdir($this->cachePath, 0755, true);

        $this->cache = $this->makeFileCache('tag_');

        $this->tags = new CacheTagManager($this->cache);
    }

    public function test_remember_indexes_keys_and_retrieves_value(): void
    {
        $value = $this->tags->remember('example', 'cache:key', 60, fn () => 'payload');
        $this->assertSame('payload', $value);

        $cached = $this->tags->remember('example', 'cache:key', 60, fn () => 'other');
        $this->assertSame('payload', $cached);
    }

    public function test_flush_removes_cached_entries(): void
    {
        $this->tags->set(['alpha', 'beta'], 'cache:key:1', 'first', 60);
        $this->tags->set('alpha', 'cache:key:2', 'second', 60);

        $this->tags->flush('alpha');

        $this->assertNull($this->cache->get('cache:key:1'));
        $this->assertNull($this->cache->get('cache:key:2'));

        // Tag beta should retain entries not flushed
        $this->tags->set('beta', 'cache:key:3', 'third', 60);
        $this->assertSame('third', $this->cache->get('cache:key:3'));
    }
}
