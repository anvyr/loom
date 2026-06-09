<?php

declare(strict_types=1);

namespace Anvyr\Loom\Tests\Unit\Database;

use Anvyr\Loom\Tests\Support\QueryBuilderTestCase;

final class QueryBuilderPaginationTest extends QueryBuilderTestCase
{
    public function test_paginate_returns_correct_structure(): void
    {
        for ($index = 1; $index <= 10; $index++) {
            $this->insertUsers([
                'name' => "User{$index}",
                'email' => "page{$index}@test.com",
            ]);
        }

        $result = $this->builder()->table('users')->paginate(3, 1);

        $this->assertArrayHasKey('data', $result);
        $this->assertArrayHasKey('total', $result);
        $this->assertArrayHasKey('per_page', $result);
        $this->assertArrayHasKey('current_page', $result);
        $this->assertArrayHasKey('last_page', $result);
        $this->assertSame(3, $result['per_page']);
        $this->assertSame(1, $result['current_page']);
        $this->assertSame(10, $result['total']);
        $this->assertSame(4, $result['last_page']);
        $this->assertCount(3, $result['data']->all());
    }

    public function test_paginate_second_page(): void
    {
        for ($index = 1; $index <= 5; $index++) {
            $this->insertUsers([
                'name' => "PgUser{$index}",
                'email' => "pg{$index}@test.com",
            ]);
        }

        $result = $this->builder()->table('users')->paginate(2, 2);

        $this->assertSame(2, $result['current_page']);
        $this->assertSame(3, $result['last_page']);
        $this->assertCount(2, $result['data']->all());
    }

    public function test_paginate_last_page_partial(): void
    {
        for ($index = 1; $index <= 7; $index++) {
            $this->insertUsers([
                'name' => "Last{$index}",
                'email' => "last{$index}@test.com",
            ]);
        }

        $result = $this->builder()->table('users')->paginate(3, 3);

        $this->assertSame(3, $result['current_page']);
        $this->assertSame(3, $result['last_page']);
        $this->assertCount(1, $result['data']->all());
    }

    public function test_paginate_empty_table(): void
    {
        $result = $this->builder()->table('posts')->paginate(10, 1);

        $this->assertSame(0, $result['total']);
        $this->assertSame(1, $result['last_page']);
        $this->assertSame(1, $result['current_page']);
        $this->assertCount(0, $result['data']->all());
    }

    public function test_paginate_with_where_clause(): void
    {
        for ($index = 1; $index <= 6; $index++) {
            $this->insertUsers([
                'name' => "Filtered{$index}",
                'email' => "filter{$index}@test.com",
                'status' => $index <= 4 ? 'active' : 'inactive',
            ]);
        }

        $result = $this->builder()
            ->table('users')
            ->where('status', '=', 'active')
            ->paginate(2, 1);

        $this->assertSame(4, $result['total']);
        $this->assertSame(2, $result['last_page']);
        $this->assertCount(2, $result['data']->all());
    }

    public function test_paginate_clamps_page_to_minimum_one(): void
    {
        $this->insertUsers([
            'name' => 'Clamp',
            'email' => 'clamp@test.com',
        ]);

        $result = $this->builder()->table('users')->paginate(10, 0);

        $this->assertSame(1, $result['current_page']);
    }

    public function test_paginate_defaults(): void
    {
        for ($index = 1; $index <= 20; $index++) {
            $this->insertUsers([
                'name' => "Def{$index}",
                'email' => "def{$index}@test.com",
            ]);
        }

        $result = $this->builder()->table('users')->paginate();

        $this->assertSame(15, $result['per_page']);
        $this->assertSame(1, $result['current_page']);
        $this->assertCount(15, $result['data']->all());
        $this->assertSame(2, $result['last_page']);
    }
}
