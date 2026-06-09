<?php

declare(strict_types=1);

namespace Anvyr\Loom\Tests\Support;

use Anvyr\Loom\Database\Connection;
use Anvyr\Loom\Database\QueryBuilder;
use Anvyr\Loom\Tests\Support\Concerns\CreatesTestDatabase;
use Anvyr\Loom\Tests\Support\Concerns\ReflectionHelpers;

abstract class QueryBuilderTestCase extends TestCase
{
    use CreatesTestDatabase;
    use ReflectionHelpers;

    protected Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = $this->makeSqliteConnection();

        $this->connection->statement('CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY,
            name TEXT NOT NULL,
            email TEXT UNIQUE,
            status TEXT DEFAULT "active",
            score INTEGER DEFAULT 0,
            created_at TEXT
        )');

        $this->connection->statement('CREATE TABLE IF NOT EXISTS posts (
            id INTEGER PRIMARY KEY,
            user_id INTEGER,
            title TEXT,
            views INTEGER DEFAULT 0
        )');
    }

    protected function builder(): QueryBuilder
    {
        return new QueryBuilder($this->connection);
    }

    protected function bindings(QueryBuilder $queryBuilder): array
    {
        return $this->getPrivateProperty($queryBuilder, 'bindings');
    }

    protected function insertUsers(array ...$users): void
    {
        foreach ($users as $user) {
            $this->builder()->table('users')->insert($user);
        }
    }
}
