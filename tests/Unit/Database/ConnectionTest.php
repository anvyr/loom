<?php

declare(strict_types=1);

namespace Anvyr\Loom\Tests\Unit\Database;

use Anvyr\Loom\Database\Connection;
use Anvyr\Loom\Tests\Support\Concerns\CreatesTestDatabase;
use Anvyr\Loom\Tests\Support\TestCase;

final class ConnectionTest extends TestCase
{
    use CreatesTestDatabase;

    private Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = $this->makeSqliteConnection('connection-test');
    }

    public function test_get_pdo_returns_pdo_instance(): void
    {
        $pdo = $this->connection->getPdo();

        $this->assertInstanceOf(\PDO::class, $pdo);
    }

    public function test_get_driver_returns_driver_name(): void
    {
        $driver = $this->connection->getDriver();

        $this->assertSame('sqlite', $driver);
    }

    public function test_query_executes_select(): void
    {
        $this->connection->statement('CREATE TABLE test_query (id INTEGER PRIMARY KEY, name TEXT)');
        $this->connection->statement('INSERT INTO test_query (name) VALUES (?)', ['Test']);

        $results = $this->connection->query('SELECT * FROM test_query WHERE name = ?', ['Test']);

        $this->assertCount(1, $results);
        $this->assertSame('Test', $results[0]['name']);
    }

    public function test_statement_returns_affected_rows(): void
    {
        $this->connection->statement('CREATE TABLE test_statement (id INTEGER PRIMARY KEY, name TEXT)');
        $this->connection->statement('INSERT INTO test_statement (name) VALUES (?)', ['One']);
        $this->connection->statement('INSERT INTO test_statement (name) VALUES (?)', ['Two']);

        $affected = $this->connection->statement('UPDATE test_statement SET name = ?', ['Updated']);

        $this->assertSame(2, $affected);
    }

    public function test_table_exists_returns_true_for_existing_table(): void
    {
        $this->connection->statement('CREATE TABLE exists_test (id INTEGER)');

        $this->assertTrue($this->connection->tableExists('exists_test'));
    }

    public function test_table_exists_returns_false_for_missing_table(): void
    {
        $this->assertFalse($this->connection->tableExists('nonexistent_table'));
    }

    public function test_last_insert_id(): void
    {
        $this->connection->statement('CREATE TABLE insert_id_test (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT)');
        $this->connection->statement('INSERT INTO insert_id_test (name) VALUES (?)', ['First']);

        $id = $this->connection->lastInsertId();

        $this->assertSame('1', $id);
    }

    public function test_transaction_commits_on_success(): void
    {
        $this->connection->statement('CREATE TABLE tx_test (id INTEGER PRIMARY KEY, name TEXT)');

        $result = $this->connection->transaction(function ($conn) {
            $conn->statement('INSERT INTO tx_test (name) VALUES (?)', ['One']);
            $conn->statement('INSERT INTO tx_test (name) VALUES (?)', ['Two']);
            return 'done';
        });

        $this->assertSame('done', $result);

        $rows = $this->connection->query('SELECT * FROM tx_test');
        $this->assertCount(2, $rows);
    }

    public function test_transaction_rollbacks_on_exception(): void
    {
        $this->connection->statement('CREATE TABLE tx_rollback_test (id INTEGER PRIMARY KEY, name TEXT)');

        try {
            $this->connection->transaction(function ($conn) {
                $conn->statement('INSERT INTO tx_rollback_test (name) VALUES (?)', ['Before Error']);
                throw new \RuntimeException('Simulated error');
            });
        } catch (\RuntimeException $e) {
            // Expected
        }

        $rows = $this->connection->query('SELECT * FROM tx_rollback_test');
        $this->assertCount(0, $rows);
    }

    public function test_manual_transaction_methods(): void
    {
        $this->connection->statement('CREATE TABLE manual_tx (id INTEGER PRIMARY KEY, name TEXT)');

        $this->connection->beginTransaction();
        $this->connection->statement('INSERT INTO manual_tx (name) VALUES (?)', ['Test']);
        $this->connection->rollback();

        $rows = $this->connection->query('SELECT * FROM manual_tx');
        $this->assertCount(0, $rows);

        $this->connection->beginTransaction();
        $this->connection->statement('INSERT INTO manual_tx (name) VALUES (?)', ['Committed']);
        $this->connection->commit();

        $rows = $this->connection->query('SELECT * FROM manual_tx');
        $this->assertCount(1, $rows);
    }

    public function test_table_method_returns_query_builder(): void
    {
        $builder = $this->connection->table('users');

        $this->assertInstanceOf(\Anvyr\Loom\Database\QueryBuilder::class, $builder);
    }

    public function test_unsupported_driver_throws(): void
    {
        $config = [
            'default' => 'oracle',
            'connections' => [
                'oracle' => [
                    'driver' => 'oracle',
                    'database' => 'test',
                ],
            ],
        ];

        $connection = new Connection($config);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unsupported driver');

        $connection->getPdo();
    }
}
