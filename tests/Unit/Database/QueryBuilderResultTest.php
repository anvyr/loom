<?php

declare(strict_types=1);

namespace Anvyr\Loom\Tests\Unit\Database;

use Anvyr\Loom\Database\Collection;
use Anvyr\Loom\Tests\Support\QueryBuilderTestCase;

final class QueryBuilderResultTest extends QueryBuilderTestCase
{
    public function test_count(): void
    {
        $this->insertUsers(
            ['name' => 'Count1', 'email' => 'c1@test.com'],
            ['name' => 'Count2', 'email' => 'c2@test.com'],
        );

        $count = $this->builder()->table('users')->count();

        $this->assertGreaterThanOrEqual(2, $count);
    }

    public function test_exists_returns_true_when_records_exist(): void
    {
        $this->insertUsers(['name' => 'Exists', 'email' => 'exists@test.com']);

        $exists = $this->builder()
            ->table('users')
            ->where('email', '=', 'exists@test.com')
            ->exists();

        $this->assertTrue($exists);
    }

    public function test_exists_returns_false_when_no_records(): void
    {
        $exists = $this->builder()
            ->table('users')
            ->where('email', '=', 'nonexistent@test.com')
            ->exists();

        $this->assertFalse($exists);
    }

    public function test_first_returns_single_row(): void
    {
        $this->insertUsers(['name' => 'First', 'email' => 'first@test.com']);

        $user = $this->builder()->table('users')->where('email', '=', 'first@test.com')->first();

        $this->assertIsArray($user);
        $this->assertSame('First', $user['name']);
    }

    public function test_first_returns_null_when_empty(): void
    {
        $result = $this->builder()
            ->table('users')
            ->where('email', '=', 'nobody@nowhere.com')
            ->first();

        $this->assertNull($result);
    }

    public function test_get_returns_collection(): void
    {
        $this->insertUsers(
            ['name' => 'Get1', 'email' => 'get1@test.com'],
            ['name' => 'Get2', 'email' => 'get2@test.com'],
        );

        $results = $this->builder()
            ->table('users')
            ->whereIn('email', ['get1@test.com', 'get2@test.com'])
            ->get();

        $this->assertInstanceOf(Collection::class, $results);
        $this->assertSame(2, $results->count());
    }

    public function test_find_by_id(): void
    {
        $this->insertUsers(['name' => 'FindMe', 'email' => 'find@test.com']);

        $user = $this->builder()->table('users')->where('email', '=', 'find@test.com')->first();
        $found = $this->builder()->table('users')->find($user['id']);

        $this->assertSame('FindMe', $found['name']);
    }

    public function test_pluck_returns_single_column(): void
    {
        $this->insertUsers(
            ['name' => 'Pluck1', 'email' => 'pluck1@test.com'],
            ['name' => 'Pluck2', 'email' => 'pluck2@test.com'],
        );

        $names = $this->builder()
            ->table('users')
            ->whereIn('email', ['pluck1@test.com', 'pluck2@test.com'])
            ->pluck('name');

        $this->assertContains('Pluck1', $names->all());
        $this->assertContains('Pluck2', $names->all());
    }
}
