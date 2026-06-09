<?php

declare(strict_types=1);

namespace Anvyr\Loom\Tests\External\Cache;

use Anvyr\Loom\Drivers\Cache\ApcuCache;
use Anvyr\Loom\Tests\Support\TestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\Attributes\RequiresSetting;

#[Group('external')]
#[RequiresPhpExtension('apcu')]
#[RequiresSetting('apc.enabled', '1')]
#[RequiresSetting('apc.enable_cli', '1')]
final class ApcuCacheTest extends TestCase
{
    private ?ApcuCache $cache = null;
    private string $prefix;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prefix = 'test_' . uniqid('', true);
        $this->cache = new ApcuCache(['prefix' => $this->prefix]);
        $this->cache->clear();
    }

    protected function tearDown(): void
    {
        if ($this->cache !== null) {
            $this->cache->clear();
        }

        parent::tearDown();
    }

    public function test_can_store_and_retrieve_values(): void
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
        $data = ['name' => 'Loom', 'version' => 1];
        $this->cache->set('array', $data);
        $this->assertSame($data, $this->cache->get('array'));
    }

    public function test_stores_integer_values(): void
    {
        $this->cache->set('int', 42);
        $this->assertSame(42, $this->cache->get('int'));
    }

    public function test_stores_null_value(): void
    {
        $this->cache->set('null', null);
        $this->assertNull($this->cache->get('null', 'default'));
    }

    public function test_prefix_isolates_keys(): void
    {
        $otherCache = new ApcuCache(['prefix' => 'other_' . uniqid('', true)]);

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
}
