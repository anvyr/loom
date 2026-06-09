<?php

declare(strict_types=1);

namespace Anvyr\Loom\Tests\Integration\Data;

use Anvyr\Loom\Drivers\Data\FileDataStore;
use Anvyr\Loom\Tests\Support\TestCase;

final class FileDataStoreTest extends TestCase
{
    private FileDataStore $store;
    private string $dataPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dataPath = $this->tmpDir . '/data';
        $this->store = new FileDataStore($this->dataPath);
    }

    public function test_can_put_and_get_data(): void
    {
        $data = ['name' => 'Test', 'active' => true];
        $this->store->put('settings', 'site', $data);

        $retrieved = $this->store->get('settings', 'site');

        $this->assertArrayHasKey('_key', $retrieved);
        $this->assertArrayHasKey('_updated_at', $retrieved);

        unset($retrieved['_key'], $retrieved['_updated_at'], $retrieved['_created_at']);
        $this->assertSame($data, $retrieved);
    }

    public function test_returns_null_for_missing_key(): void
    {
        $this->assertNull($this->store->get('settings', 'missing'));
    }

    public function test_can_check_existence(): void
    {
        $this->store->put('users', '1', ['id' => 1]);

        $this->assertTrue($this->store->has('users', '1'));
        $this->assertFalse($this->store->has('users', '2'));
    }

    public function test_can_forget_item(): void
    {
        $this->store->put('temp', 'key', ['foo' => 'bar']);
        $this->assertTrue($this->store->forget('temp', 'key'));

        $this->assertNull($this->store->get('temp', 'key'));
        $this->assertFalse($this->store->forget('temp', 'key')); // Already gone
    }

    public function test_can_list_all_items_in_collection(): void
    {
        $this->store->put('posts', '1', ['title' => 'One']);
        $this->store->put('posts', '2', ['title' => 'Two']);

        $all = $this->store->all('posts');

        $this->assertCount(2, $all);
        $this->assertArrayHasKey('1', $all);
        $this->assertArrayHasKey('2', $all);
    }

    public function test_persists_to_disk(): void
    {
        $this->store->put('disk', 'test', ['saved' => true]);

        // Create new instance to verify persistence
        $newStore = new FileDataStore($this->dataPath);
        $retrieved = $newStore->get('disk', 'test');

        unset($retrieved['_key'], $retrieved['_updated_at'], $retrieved['_created_at']);
        $this->assertSame(['saved' => true], $retrieved);

        $this->assertFileExists($this->dataPath . '/disk/test.json');
    }
}
