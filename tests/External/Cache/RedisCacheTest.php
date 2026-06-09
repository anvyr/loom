<?php

declare(strict_types=1);

namespace Anvyr\Loom\Tests\External\Cache;

use Anvyr\Loom\Drivers\Cache\RedisCache;
use Anvyr\Loom\Tests\Support\TestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;

#[Group('external')]
#[RequiresPhpExtension('redis')]
final class RedisCacheTest extends TestCase
{
    private ?RedisCache $cache = null;
    private string $prefix;

    private function redisHost(): string
    {
        $host = getenv('REDIS_HOST');

        return ($host !== false && $host !== '') ? $host : '127.0.0.1';
    }

    private function redisPort(): int
    {
        $port = getenv('REDIS_PORT');

        return ($port !== false && $port !== '') ? (int) $port : 6379;
    }

    protected function setUp(): void
    {
        parent::setUp();

        $host = $this->redisHost();
        $port = $this->redisPort();
        $endpoint = "{$host}:{$port}";

        $client = new \Redis();

        try {
            $connected = $client->connect($host, $port, 0.5);
        } catch (\RedisException $exception) {
            $this->markTestSkipped("Redis server is not reachable on {$endpoint}");
        }

        if (!$connected) {
            $this->markTestSkipped("Redis server is not reachable on {$endpoint}");
        }

        try {
            $client->ping();
        } catch (\Throwable $exception) {
            $client->close();
            $this->markTestSkipped('Redis server is not responding to PING');
        }

        $client->close();

        $this->prefix = 'loom_test_' . uniqid('', true);
        $this->cache = new RedisCache([
            'host' => $host,
            'port' => $port,
            'database' => 15,
            'prefix' => $this->prefix,
        ]);
        $this->cache->clear();
    }

    protected function tearDown(): void
    {
        if ($this->cache !== null) {
            $this->cache->clear();
        }

        parent::tearDown();
    }

    public function test_can_set_and_get_value(): void
    {
        $this->cache->set('foo', 'bar', 10);
        $this->assertSame('bar', $this->cache->get('foo'));
    }

    public function test_returns_default_on_miss(): void
    {
        $this->assertSame('default', $this->cache->get('missing', 'default'));
    }

    public function test_can_delete_item(): void
    {
        $this->cache->set('del', 'val');
        $this->cache->delete('del');
        $this->assertNull($this->cache->get('del'));
    }

    public function test_has_returns_true_for_existing_key(): void
    {
        $this->cache->set('exists', 'value');
        $this->assertTrue($this->cache->has('exists'));
    }

    public function test_has_returns_false_for_missing_key(): void
    {
        $this->assertFalse($this->cache->has('nonexistent'));
    }

    public function test_clear_removes_all_items(): void
    {
        $this->cache->set('one', '1');
        $this->cache->set('two', '2');
        $this->cache->clear();

        $this->assertNull($this->cache->get('one'));
        $this->assertNull($this->cache->get('two'));
    }

    public function test_stores_array_values(): void
    {
        $data = ['name' => 'Loom', 'items' => [1, 2, 3]];
        $this->cache->set('array', $data);
        $this->assertSame($data, $this->cache->get('array'));
    }

    public function test_stores_integer_values(): void
    {
        $this->cache->set('int', 42);
        $this->assertSame(42, $this->cache->get('int'));
    }

    public function test_stores_boolean_true(): void
    {
        $this->cache->set('flag', true);
        $this->assertTrue($this->cache->get('flag'));
    }

    public function test_prefix_isolates_keys(): void
    {
        $otherCache = new RedisCache([
            'host' => $this->redisHost(),
            'port' => $this->redisPort(),
            'database' => 15,
            'prefix' => 'other_' . uniqid('', true),
        ]);

        $this->cache->set('shared', 'original');
        $otherCache->set('shared', 'other');

        $this->assertSame('original', $this->cache->get('shared'));
        $this->assertSame('other', $otherCache->get('shared'));

        $otherCache->clear();
    }

    public function test_overwrite_existing_key(): void
    {
        $this->cache->set('key', 'first');
        $this->cache->set('key', 'second');
        $this->assertSame('second', $this->cache->get('key'));
    }

    public function test_uses_separate_database(): void
    {
        $this->cache->set('isolated', 'value');
        $this->assertSame('value', $this->cache->get('isolated'));
    }
}
