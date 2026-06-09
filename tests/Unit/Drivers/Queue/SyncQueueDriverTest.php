<?php

declare(strict_types=1);

namespace Anvyr\Loom\Tests\Unit\Drivers\Queue;

use Anvyr\Loom\Drivers\Queue\SyncQueueDriver;
use Anvyr\Loom\Queue\Job;
use Anvyr\Loom\Tests\Support\TestCase;

final class SyncQueueDriverTest extends TestCase
{
    private SyncQueueDriver $driver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->driver = new SyncQueueDriver();
    }

    public function test_push_executes_job_handle_immediately(): void
    {
        $job = new SyncTestJob();

        $this->driver->push($job);

        $this->assertTrue($job->handled);
    }

    public function test_push_returns_incrementing_ids(): void
    {
        $id1 = $this->driver->push(new SyncTestJob());
        $id2 = $this->driver->push(new SyncTestJob());

        $this->assertSame('1', $id1);
        $this->assertSame('2', $id2);
    }

    public function test_push_sets_job_id_and_attempts(): void
    {
        $job = new SyncTestJob();

        $this->driver->push($job);

        $this->assertSame('1', $job->id);
        $this->assertSame(1, $job->attempts);
    }

    public function test_push_calls_failed_on_exception(): void
    {
        $job = new FailingSyncTestJob();

        try {
            $this->driver->push($job);
            $this->fail('Expected exception was not thrown');
        } catch (\RuntimeException) {
            $this->assertTrue($job->failedCalled);
        }
    }

    public function test_push_rethrows_exception(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Job failed');

        $this->driver->push(new FailingSyncTestJob());
    }

    public function test_pop_always_returns_null(): void
    {
        $this->assertNull($this->driver->pop());
        $this->assertNull($this->driver->pop('custom'));
    }

    public function test_size_always_returns_zero(): void
    {
        $this->assertSame(0, $this->driver->size());
        $this->assertSame(0, $this->driver->size('custom'));
    }

    public function test_clear_always_returns_zero(): void
    {
        $this->assertSame(0, $this->driver->clear());
        $this->assertSame(0, $this->driver->clear('custom'));
    }

    public function test_complete_fail_release_are_noops(): void
    {
        $this->driver->complete('1');
        $this->driver->fail('1', new \RuntimeException('test'));
        $this->driver->release('1', 60);

        $this->addToAssertionCount(1);
    }
}

class SyncTestJob extends Job
{
    public bool $handled = false;

    public function handle(): void
    {
        $this->handled = true;
    }
}

class FailingSyncTestJob extends Job
{
    public bool $failedCalled = false;

    public function handle(): void
    {
        throw new \RuntimeException('Job failed');
    }

    public function failed(\Throwable $exception): void
    {
        $this->failedCalled = true;
    }
}
