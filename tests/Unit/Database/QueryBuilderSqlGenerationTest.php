<?php

declare(strict_types=1);

namespace Anvyr\Loom\Tests\Unit\Database;

use Anvyr\Loom\Tests\Support\QueryBuilderTestCase;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;

final class QueryBuilderSqlGenerationTest extends QueryBuilderTestCase
{
    public function test_basic_select(): void
    {
        $sql = $this->builder()
            ->table('users')
            ->select('id', 'name')
            ->toSql();

        $this->assertSame('SELECT id, name FROM users', $sql);
    }

    public function test_select_with_array(): void
    {
        $sql = $this->builder()
            ->table('users')
            ->select(['id', 'name', 'email'])
            ->toSql();

        $this->assertSame('SELECT id, name, email FROM users', $sql);
    }

    public function test_select_raw(): void
    {
        $queryBuilder = $this->builder()
            ->table('users')
            ->selectRaw('COUNT(*) as total, AVG(score) as avg_score');

        $this->assertStringContainsString('COUNT(*) as total', $queryBuilder->toSql());
    }

    public function test_where_with_equals_operator(): void
    {
        $queryBuilder = $this->builder()
            ->table('users')
            ->where('status', '=', 'active');

        $this->assertSame('SELECT * FROM users WHERE status = ?', $queryBuilder->toSql());
        $this->assertSame(['active'], $this->bindings($queryBuilder));
    }

    public function test_where_with_comparison_operator(): void
    {
        $queryBuilder = $this->builder()
            ->table('users')
            ->where('score', '>', 100);

        $this->assertSame('SELECT * FROM users WHERE score > ?', $queryBuilder->toSql());
        $this->assertSame([100], $this->bindings($queryBuilder));
    }

    public function test_or_where(): void
    {
        $queryBuilder = $this->builder()
            ->table('users')
            ->where('status', '=', 'active')
            ->orWhere('status', '=', 'pending');

        $this->assertStringContainsString('OR status = ?', $queryBuilder->toSql());
    }

    public function test_where_in(): void
    {
        $queryBuilder = $this->builder()
            ->table('users')
            ->whereIn('id', [1, 2, 3]);

        $this->assertSame('SELECT * FROM users WHERE id IN (?, ?, ?)', $queryBuilder->toSql());
        $this->assertSame([1, 2, 3], $this->bindings($queryBuilder));
    }

    public function test_where_null(): void
    {
        $sql = $this->builder()
            ->table('users')
            ->whereNull('email')
            ->toSql();

        $this->assertSame('SELECT * FROM users WHERE email IS NULL', $sql);
    }

    public function test_where_not_null(): void
    {
        $sql = $this->builder()
            ->table('users')
            ->whereNotNull('email')
            ->toSql();

        $this->assertSame('SELECT * FROM users WHERE email IS NOT NULL', $sql);
    }

    public function test_where_raw(): void
    {
        $queryBuilder = $this->builder()
            ->table('users')
            ->whereRaw('score > ? AND score < ?', [50, 100]);

        $this->assertStringContainsString('score > ? AND score < ?', $queryBuilder->toSql());
        $this->assertSame([50, 100], $this->bindings($queryBuilder));
    }

    public function test_inner_join(): void
    {
        $sql = $this->builder()
            ->table('users')
            ->join('posts', 'users.id', '=', 'posts.user_id')
            ->toSql();

        $this->assertStringContainsString('INNER JOIN posts ON users.id = posts.user_id', $sql);
    }

    public function test_left_join(): void
    {
        $sql = $this->builder()
            ->table('users')
            ->leftJoin('posts', 'users.id', '=', 'posts.user_id')
            ->toSql();

        $this->assertStringContainsString('LEFT JOIN posts ON users.id = posts.user_id', $sql);
    }

    public function test_group_by(): void
    {
        $sql = $this->builder()
            ->table('posts')
            ->select('user_id')
            ->selectRaw('COUNT(*) as post_count')
            ->groupBy('user_id')
            ->toSql();

        $this->assertStringContainsString('GROUP BY user_id', $sql);
    }

    public function test_having(): void
    {
        $queryBuilder = $this->builder()
            ->table('posts')
            ->select('user_id')
            ->selectRaw('COUNT(*) as post_count')
            ->groupBy('user_id')
            ->having('post_count', '>', 5);

        $this->assertStringContainsString('HAVING post_count > ?', $queryBuilder->toSql());
        $this->assertContains(5, $this->bindings($queryBuilder));
    }

    public function test_order_by(): void
    {
        $sql = $this->builder()
            ->table('users')
            ->orderBy('name', 'ASC')
            ->toSql();

        $this->assertStringContainsString('ORDER BY name ASC', $sql);
    }

    public function test_order_by_desc(): void
    {
        $sql = $this->builder()
            ->table('users')
            ->orderBy('created_at', 'DESC')
            ->toSql();

        $this->assertStringContainsString('ORDER BY created_at DESC', $sql);
    }

    public function test_limit_and_offset(): void
    {
        $sql = $this->builder()
            ->table('users')
            ->limit(10)
            ->offset(20)
            ->toSql();

        $this->assertStringContainsString('LIMIT 10', $sql);
        $this->assertStringContainsString('OFFSET 20', $sql);
    }

    #[DataProvider('provideInvalidReferenceCases')]
    public function test_rejects_invalid_references(string $case): void
    {
        $this->expectException(InvalidArgumentException::class);

        match ($case) {
            'order_by' => $this->builder()->table('users')->orderBy('name; DROP TABLE users;--')->toSql(),
            'table' => $this->builder()->table('users; DROP TABLE users;--')->toSql(),
            'where' => $this->builder()->table('users')->where('name; DROP TABLE users;--', '=', 'x')->toSql(),
        };
    }

    public static function provideInvalidReferenceCases(): array
    {
        return [
            'invalid order by column' => ['order_by'],
            'invalid table reference' => ['table'],
            'invalid where column' => ['where'],
        ];
    }
}
