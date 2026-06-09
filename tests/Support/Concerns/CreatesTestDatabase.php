<?php

declare(strict_types=1);

namespace Anvyr\Loom\Tests\Support\Concerns;

use Anvyr\Loom\Database\Connection;

/**
 * Helpers for creating in-memory or file-based SQLite databases in tests.
 *
 * Requires the using class to have a `$tmpDir` property (provided by TestCase).
 */
trait CreatesTestDatabase
{
    /**
     * Create a SQLite Connection backed by a temp file.
     */
    protected function makeSqliteConnection(string $name = 'test'): Connection
    {
        return new Connection([
            'default' => 'sqlite',
            'connections' => [
                'sqlite' => [
                    'driver' => 'sqlite',
                    'database' => $this->tmpDir . "/{$name}.sqlite",
                ],
            ],
        ]);
    }

    /**
     * Create the `data_store` table used by DatabaseDataStore.
     */
    protected function createDataStoreTable(\PDO $pdo): void
    {
        $pdo->exec('
            CREATE TABLE data_store (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                collection VARCHAR(255) NOT NULL,
                key VARCHAR(255) NOT NULL,
                data TEXT NOT NULL,
                created_at DATETIME,
                updated_at DATETIME,
                UNIQUE(collection, key)
            )
        ');
    }
}
