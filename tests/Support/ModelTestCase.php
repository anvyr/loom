<?php

declare(strict_types=1);

namespace Anvyr\Loom\Tests\Support;

use Anvyr\Loom\Database\Connection;
use Anvyr\Loom\Database\Model;
use Anvyr\Loom\Tests\Support\Concerns\CreatesTestDatabase;

abstract class ModelTestCase extends TestCase
{
    use CreatesTestDatabase;

    protected Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = $this->makeSqliteConnection();

        Model::setConnectionResolver(fn () => $this->connection);

        $this->createTables();
        $this->seedData();
    }

    protected function tearDown(): void
    {
        Model::setConnectionResolver(fn () => $this->connection);
        parent::tearDown();
    }

    protected function createTables(): void
    {
        $this->connection->statement('
            CREATE TABLE IF NOT EXISTS users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                email TEXT UNIQUE,
                is_active INTEGER DEFAULT 1,
                settings TEXT,
                score REAL DEFAULT 0,
                created_at TEXT,
                updated_at TEXT
            )
        ');

        $this->connection->statement('
            CREATE TABLE IF NOT EXISTS posts (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                title TEXT NOT NULL,
                body TEXT DEFAULT "",
                is_published INTEGER DEFAULT 0,
                deleted_at TEXT,
                created_at TEXT,
                updated_at TEXT,
                FOREIGN KEY (user_id) REFERENCES users(id)
            )
        ');

        $this->connection->statement('
            CREATE TABLE IF NOT EXISTS profiles (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER UNIQUE NOT NULL,
                bio TEXT DEFAULT "",
                website TEXT DEFAULT "",
                created_at TEXT,
                updated_at TEXT,
                FOREIGN KEY (user_id) REFERENCES users(id)
            )
        ');

        $this->connection->statement('
            CREATE TABLE IF NOT EXISTS roles (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL UNIQUE,
                created_at TEXT,
                updated_at TEXT
            )
        ');

        $this->connection->statement('
            CREATE TABLE IF NOT EXISTS role_user (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                role_id INTEGER NOT NULL,
                created_at TEXT,
                updated_at TEXT,
                FOREIGN KEY (user_id) REFERENCES users(id),
                FOREIGN KEY (role_id) REFERENCES roles(id)
            )
        ');
    }

    protected function seedData(): void
    {
    }
}
