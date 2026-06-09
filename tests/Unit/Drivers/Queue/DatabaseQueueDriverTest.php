<?php

declare(strict_types=1);

namespace Anvyr\Loom\Tests\Unit\Drivers\Queue;

use Anvyr\Loom\Contracts\ShouldBeUnique;
use Anvyr\Loom\Database\Connection;
use Anvyr\Loom\Drivers\Queue\DatabaseQueueDriver;
use Anvyr\Loom\Queue\Job;
use Anvyr\Loom\Tests\Support\Concerns\CreatesTestDatabase;
use Anvyr\Loom\Tests\Support\TestCase;

final class DatabaseQueueDriverTest extends TestCase
{
    use CreatesTestDatabase;

    private Connection $db;
    private DatabaseQueueDriver $driver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->db = $this->makeSqliteConnection('queue');
        $this->createQueueTables($this->db->getPdo());

        $this->driver = new DatabaseQueueDriver($this->db);
    }

    public function test_push_inserts_job_and_returns_id(): void
    {
        $job = new DbTestJob('hello');
        $id = $this->driver->push($job);

        $this->assertSame('1', $id);

        $rows = $this->db->query('SELECT * FROM jobs WHERE id = ?', [(int) $id]);
        $this->assertCount(1, $rows);
        $this->assertSame('default', $rows[0]['queue']);
        $this->assertSame(0, (int) $rows[0]['attempts']);
    }

    public function test_push_respects_queue_and_delay(): void
    {
        $job = new DbTestJob('test');
        $this->driver->push($job, 'emails', 60);

        $rows = $this->db->query('SELECT * FROM jobs WHERE queue = ?', ['emails']);
        $this->assertCount(1, $rows);
    }

    public function test_push_unique_job_deduplicates(): void
    {
        $job1 = new UniqueDbTestJob('user-42');
        $job2 = new UniqueDbTestJob('user-42');

        $id1 = $this->driver->push($job1);
        $id2 = $this->driver->push($job2);

        $this->assertSame($id1, $id2);

        $count = $this->db->query('SELECT COUNT(*) as cnt FROM jobs');
        $this->assertSame(1, (int) $count[0]['cnt']);
    }

    public function test_push_unique_job_allows_different_ids(): void
    {
        $job1 = new UniqueDbTestJob('user-42');
        $job2 = new UniqueDbTestJob('user-99');

        $id1 = $this->driver->push($job1);
        $id2 = $this->driver->push($job2);

        $this->assertNotSame($id1, $id2);
    }

    public function test_pop_returns_null_when_empty(): void
    {
        $this->assertNull($this->driver->pop());
    }

    public function test_pop_returns_job_and_reserves_it(): void
    {
        $job = new DbTestJob('hello');
        $this->driver->push($job);

        $popped = $this->driver->pop();

        $this->assertInstanceOf(DbTestJob::class, $popped);
        $this->assertSame('1', $popped->id);
        $this->assertSame(1, $popped->attempts);
        $this->assertSame('hello', $popped->message);

        // Should be reserved now
        $rows = $this->db->query('SELECT reserved_at FROM jobs WHERE id = 1');
        $this->assertNotNull($rows[0]['reserved_at']);
    }

    public function test_pop_does_not_return_reserved_job(): void
    {
        $job = new DbTestJob('hello');
        $this->driver->push($job);

        $first = $this->driver->pop();
        $second = $this->driver->pop();

        $this->assertNotNull($first);
        $this->assertNull($second);
    }

    public function test_pop_skips_delayed_jobs(): void
    {
        $job = new DbTestJob('delayed');
        $this->driver->push($job, 'default', 3600);

        $this->assertNull($this->driver->pop());
    }

    public function test_pop_respects_queue(): void
    {
        $this->driver->push(new DbTestJob('a'), 'emails');
        $this->driver->push(new DbTestJob('b'), 'default');

        $popped = $this->driver->pop('emails');

        $this->assertNotNull($popped);
        $this->assertSame('a', $popped->message);
    }

    public function test_complete_removes_job(): void
    {
        $job = new DbTestJob('test');
        $id = $this->driver->push($job);

        $this->driver->complete($id);

        $rows = $this->db->query('SELECT * FROM jobs WHERE id = ?', [(int) $id]);
        $this->assertCount(0, $rows);
    }

    public function test_fail_moves_job_to_failed_table(): void
    {
        $job = new DbTestJob('test');
        $id = $this->driver->push($job);
        $exception = new \RuntimeException('Something broke');

        $this->driver->fail($id, $exception);

        // Removed from jobs
        $jobs = $this->db->query('SELECT * FROM jobs WHERE id = ?', [(int) $id]);
        $this->assertCount(0, $jobs);

        // Added to failed_jobs
        $failed = $this->db->query('SELECT * FROM failed_jobs');
        $this->assertCount(1, $failed);
        $this->assertStringContainsString('Something broke', $failed[0]['exception']);
        $this->assertSame('default', $failed[0]['queue']);
    }

    public function test_fail_does_nothing_for_nonexistent_job(): void
    {
        $this->driver->fail('999', new \RuntimeException('test'));

        $failed = $this->db->query('SELECT * FROM failed_jobs');
        $this->assertCount(0, $failed);
    }

    public function test_release_makes_job_available_again(): void
    {
        $job = new DbTestJob('test');
        $this->driver->push($job);
        $popped = $this->driver->pop();

        $this->driver->release($popped->id, 0);

        $rows = $this->db->query('SELECT reserved_at FROM jobs WHERE id = ?', [(int) $popped->id]);
        $this->assertNull($rows[0]['reserved_at']);

        // Should be poppable again
        $again = $this->driver->pop();
        $this->assertNotNull($again);
    }

    public function test_size_returns_job_count(): void
    {
        $this->assertSame(0, $this->driver->size());

        $this->driver->push(new DbTestJob('a'));
        $this->driver->push(new DbTestJob('b'));
        $this->driver->push(new DbTestJob('c'), 'emails');

        $this->assertSame(3, $this->driver->size());
        $this->assertSame(2, $this->driver->size('default'));
        $this->assertSame(1, $this->driver->size('emails'));
    }

    public function test_clear_removes_all_jobs(): void
    {
        $this->driver->push(new DbTestJob('a'));
        $this->driver->push(new DbTestJob('b'));

        $deleted = $this->driver->clear();

        $this->assertSame(2, $deleted);
        $this->assertSame(0, $this->driver->size());
    }

    public function test_clear_with_queue_removes_only_that_queue(): void
    {
        $this->driver->push(new DbTestJob('a'), 'default');
        $this->driver->push(new DbTestJob('b'), 'emails');

        $deleted = $this->driver->clear('default');

        $this->assertSame(1, $deleted);
        $this->assertSame(1, $this->driver->size());
    }

    public function test_pop_reclaims_stuck_jobs(): void
    {
        $job = new DbTestJob('stuck');
        $this->driver->push($job);

        // Manually reserve the job with an old timestamp
        $oldTime = date('Y-m-d H:i:s', time() - 200);
        $this->db->statement(
            'UPDATE jobs SET reserved_at = ?, attempts = 1 WHERE id = 1',
            [$oldTime]
        );

        // Pop should reclaim it
        $popped = $this->driver->pop();

        $this->assertNotNull($popped);
        $this->assertSame('stuck', $popped->message);
    }

    private function createQueueTables(\PDO $pdo): void
    {
        $pdo->exec('
            CREATE TABLE jobs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                queue VARCHAR(100) NOT NULL DEFAULT "default",
                payload TEXT NOT NULL,
                attempts INTEGER NOT NULL DEFAULT 0,
                unique_id VARCHAR(255),
                available_at DATETIME NOT NULL,
                reserved_at DATETIME,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ');

        $pdo->exec('
            CREATE TABLE failed_jobs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                queue VARCHAR(100) NOT NULL,
                payload TEXT NOT NULL,
                exception TEXT NOT NULL,
                failed_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ');

        $pdo->exec('CREATE INDEX idx_jobs_queue ON jobs (queue)');
        $pdo->exec('CREATE UNIQUE INDEX idx_jobs_unique_id ON jobs (unique_id)');
    }
}

class DbTestJob extends Job
{
    public function __construct(
        public string $message = '',
    ) {
    }

    public function handle(): void
    {
    }
}

class UniqueDbTestJob extends Job implements ShouldBeUnique
{
    public function __construct(
        public string $userId = '',
    ) {
    }

    public function handle(): void
    {
    }

    public function uniqueId(): string
    {
        return 'unique-user-' . $this->userId;
    }
}
