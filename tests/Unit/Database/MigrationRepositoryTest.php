<?php

declare(strict_types=1);

namespace Anvyr\Loom\Tests\Unit\Database;

use Anvyr\Loom\Database\Connection;
use Anvyr\Loom\Database\Migrations\MigrationRepository;
use Anvyr\Loom\Database\Schema\Schema;
use Anvyr\Loom\Tests\Support\Concerns\CreatesTestDatabase;
use Anvyr\Loom\Tests\Support\TestCase;

final class MigrationRepositoryTest extends TestCase
{
    use CreatesTestDatabase;

    private Connection $connection;
    private Schema $schema;
    private MigrationRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = $this->makeSqliteConnection('migrations-test');
        $this->schema = new Schema($this->connection);
        $this->repository = new MigrationRepository($this->connection, $this->schema);
    }

    public function test_repository_exists_returns_false_initially(): void
    {
        $this->assertFalse($this->repository->repositoryExists());
    }

    public function test_create_repository_creates_migrations_table(): void
    {
        $this->repository->createRepository();

        $this->assertTrue($this->repository->repositoryExists());
        $this->assertTrue($this->connection->tableExists('migrations'));
    }

    public function test_get_ran_returns_empty_when_no_repository(): void
    {
        $ran = $this->repository->getRan();

        $this->assertSame([], $ran);
    }

    public function test_get_ran_returns_empty_when_no_migrations(): void
    {
        $this->repository->createRepository();

        $ran = $this->repository->getRan();

        $this->assertSame([], $ran);
    }

    public function test_log_records_migration(): void
    {
        $this->repository->createRepository();

        $this->repository->log('2025_01_01_000000_create_users_table', 1);

        $ran = $this->repository->getRan();
        $this->assertContains('2025_01_01_000000_create_users_table', $ran);
    }

    public function test_log_records_multiple_migrations(): void
    {
        $this->repository->createRepository();

        $this->repository->log('2025_01_01_000000_create_users_table', 1);
        $this->repository->log('2025_01_01_000001_create_posts_table', 1);
        $this->repository->log('2025_01_02_000000_add_email_to_users', 2);

        $ran = $this->repository->getRan();

        $this->assertCount(3, $ran);
    }

    public function test_get_ran_returns_in_batch_order(): void
    {
        $this->repository->createRepository();

        $this->repository->log('migration_b', 2);
        $this->repository->log('migration_a', 1);
        $this->repository->log('migration_c', 2);

        $ran = $this->repository->getRan();

        // Should be ordered by batch first, then by migration name
        $this->assertSame('migration_a', $ran[0]);
    }

    public function test_delete_removes_migration(): void
    {
        $this->repository->createRepository();

        $this->repository->log('to_delete', 1);
        $this->repository->log('to_keep', 1);

        $this->repository->delete('to_delete');

        $ran = $this->repository->getRan();
        $this->assertNotContains('to_delete', $ran);
        $this->assertContains('to_keep', $ran);
    }

    public function test_get_last_batch_number_returns_zero_when_no_repository(): void
    {
        $lastBatch = $this->repository->getLastBatchNumber();

        $this->assertSame(0, $lastBatch);
    }

    public function test_get_last_batch_number_returns_zero_when_empty(): void
    {
        $this->repository->createRepository();

        $lastBatch = $this->repository->getLastBatchNumber();

        $this->assertSame(0, $lastBatch);
    }

    public function test_get_last_batch_number_returns_highest_batch(): void
    {
        $this->repository->createRepository();

        $this->repository->log('migration_1', 1);
        $this->repository->log('migration_2', 1);
        $this->repository->log('migration_3', 2);
        $this->repository->log('migration_4', 3);

        $lastBatch = $this->repository->getLastBatchNumber();

        $this->assertSame(3, $lastBatch);
    }

    public function test_get_next_batch_number_returns_one_when_empty(): void
    {
        $this->repository->createRepository();

        $nextBatch = $this->repository->getNextBatchNumber();

        $this->assertSame(1, $nextBatch);
    }

    public function test_get_next_batch_number_increments(): void
    {
        $this->repository->createRepository();

        $this->repository->log('migration_1', 1);
        $this->repository->log('migration_2', 2);

        $nextBatch = $this->repository->getNextBatchNumber();

        $this->assertSame(3, $nextBatch);
    }
}
