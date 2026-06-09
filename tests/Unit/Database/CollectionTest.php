<?php

declare(strict_types=1);

namespace Anvyr\Loom\Tests\Unit\Database;

use Anvyr\Loom\Database\Collection;
use Anvyr\Loom\Tests\Support\TestCase;

final class CollectionTest extends TestCase
{
    public function test_can_create_and_get_items(): void
    {
        $c = new Collection(['a', 'b', 'c']);
        $this->assertSame(['a', 'b', 'c'], $c->all());
        $this->assertSame('a', $c->first());
        $this->assertSame('c', $c->last());
    }

    public function test_map_transforms_items(): void
    {
        $c = new Collection([1, 2, 3]);
        $mapped = $c->map(fn ($n) => $n * 2);

        $this->assertSame([2, 4, 6], $mapped->all());
    }

    public function test_filter_removes_items(): void
    {
        $c = new Collection([1, 2, 3, 4]);
        $filtered = $c->filter(fn ($n) => $n % 2 === 0);

        $this->assertSame([2, 4], array_values($filtered->all()));
    }

    public function test_pluck_extracts_column(): void
    {
        $data = [
            ['id' => 1, 'name' => 'A'],
            ['id' => 2, 'name' => 'B'],
        ];
        $c = new Collection($data);

        $names = $c->pluck('name');
        $this->assertSame(['A', 'B'], $names->all());
    }

    public function test_chunk_splits_collection(): void
    {
        $c = new Collection([1, 2, 3, 4, 5]);
        $chunks = $c->chunk(2);

        $this->assertCount(3, $chunks);
        $this->assertSame([1, 2], $chunks->get(0)->all());
        $this->assertSame([5], $chunks->get(2)->all());
    }

    public function test_sort_orders_items(): void
    {
        $c = new Collection([3, 1, 2]);
        $sorted = $c->sort();

        $this->assertSame([1, 2, 3], $sorted->all());

        $desc = $c->sort(fn ($a, $b) => $b <=> $a);
        $this->assertSame([3, 2, 1], $desc->all());
    }
}
