<?php

declare(strict_types=1);

namespace Anvyr\Loom\Tests\Unit\Database;

use Anvyr\Loom\Tests\Support\QueryBuilderTestCase;

final class QueryBuilderMutationTest extends QueryBuilderTestCase
{
    public function test_insert(): void
    {
        $result = $this->builder()
            ->table('users')
            ->insert([
                'name' => 'John Doe',
                'email' => 'john@example.com',
                'score' => 100,
            ]);

        $this->assertTrue($result);

        $user = $this->builder()->table('users')->where('email', '=', 'john@example.com')->first();
        $this->assertSame('John Doe', $user['name']);
    }

    public function test_update(): void
    {
        $this->insertUsers([
            'name' => 'Jane',
            'email' => 'jane@example.com',
        ]);

        $affected = $this->builder()
            ->table('users')
            ->where('email', '=', 'jane@example.com')
            ->update(['name' => 'Jane Updated']);

        $this->assertGreaterThanOrEqual(1, $affected);

        $user = $this->builder()->table('users')->where('email', '=', 'jane@example.com')->first();
        $this->assertSame('Jane Updated', $user['name']);
    }

    public function test_delete(): void
    {
        $this->insertUsers([
            'name' => 'ToDelete',
            'email' => 'delete@example.com',
        ]);

        $affected = $this->builder()
            ->table('users')
            ->where('email', '=', 'delete@example.com')
            ->delete();

        $this->assertGreaterThanOrEqual(1, $affected);

        $user = $this->builder()->table('users')->where('email', '=', 'delete@example.com')->first();
        $this->assertNull($user);
    }

    public function test_upsert_inserts_new_record(): void
    {
        $result = $this->builder()
            ->table('users')
            ->upsert(
                ['name' => 'Upsert User', 'email' => 'upsert@example.com', 'score' => 50],
                'email',
            );

        $this->assertTrue($result);

        $user = $this->builder()->table('users')->where('email', '=', 'upsert@example.com')->first();
        $this->assertSame('Upsert User', $user['name']);
    }

    public function test_upsert_updates_existing_record(): void
    {
        $this->insertUsers([
            'name' => 'Original',
            'email' => 'upsert2@example.com',
            'score' => 10,
        ]);

        $this->builder()
            ->table('users')
            ->upsert(
                ['name' => 'Updated', 'email' => 'upsert2@example.com', 'score' => 99],
                'email',
                ['name', 'score'],
            );

        $user = $this->builder()->table('users')->where('email', '=', 'upsert2@example.com')->first();
        $this->assertSame('Updated', $user['name']);
        $this->assertSame(99, (int) $user['score']);
    }
}
