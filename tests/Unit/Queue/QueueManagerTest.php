<?php

declare(strict_types=1);

namespace Anvyr\Loom\Tests\Unit\Queue;

use Anvyr\Loom\Contracts\QueueDriver;
use Anvyr\Loom\Drivers\Queue\SyncQueueDriver;
use Anvyr\Loom\Queue\Job;
use Anvyr\Loom\Queue\PendingDispatch;
use Anvyr\Loom\Queue\QueueManager;
use Anvyr\Loom\Tests\Support\TestCase;

final class QueueManagerTest extends TestCase
{
    public function test_dispatch_returns_pending_dispatch(): void
    {
        $manager = new QueueManager(new SyncQueueDriver());
        $job = new ManagerTestJob();

        $result = $manager->dispatch($job);

        $this->assertInstanceOf(PendingDispatch::class, $result);
    }

    public function test_dispatch_now_calls_handle(): void
    {
        $manager = new QueueManager(new SyncQueueDriver());
        $job = new ManagerTestJob();

        $manager->dispatchNow($job);

        $this->assertTrue($job->handled);
    }

    public function test_dispatch_now_sets_id_and_attempts(): void
    {
        $manager = new QueueManager(new SyncQueueDriver());
        $job = new ManagerTestJob();

        $manager->dispatchNow($job);

        $this->assertStringStartsWith('sync-', $job->id);
        $this->assertSame(1, $job->attempts);
    }

    public function test_dispatch_now_calls_failed_on_exception(): void
    {
        $manager = new QueueManager(new SyncQueueDriver());
        $job = new ManagerFailingJob();

        try {
            $manager->dispatchNow($job);
        } catch (\RuntimeException) {
            // expected
        }

        $this->assertTrue($job->failedCalled);
    }

    public function test_pop_delegates_to_driver(): void
    {
        $driver = new ManagerRecordingDriver();
        $manager = new QueueManager($driver);

        $result = $manager->pop('emails');

        $this->assertNull($result);
        $this->assertSame('emails', $driver->poppedQueue);
    }

    public function test_complete_delegates_to_driver(): void
    {
        $driver = new ManagerRecordingDriver();
        $manager = new QueueManager($driver);
        $job = new ManagerTestJob();
        $job->id = '42';

        $manager->complete($job);

        $this->assertSame('42', $driver->completedId);
    }

    public function test_complete_does_nothing_for_null_id(): void
    {
        $driver = new ManagerRecordingDriver();
        $manager = new QueueManager($driver);
        $job = new ManagerTestJob();

        $manager->complete($job);

        $this->assertNull($driver->completedId);
    }

    public function test_fail_delegates_to_driver_and_calls_failed(): void
    {
        $driver = new ManagerRecordingDriver();
        $manager = new QueueManager($driver);
        $job = new ManagerTestJob();
        $job->id = '42';
        $exception = new \RuntimeException('boom');

        $manager->fail($job, $exception);

        $this->assertSame('42', $driver->failedId);
        $this->assertTrue($job->failedCalled);
    }

    public function test_release_delegates_to_driver(): void
    {
        $driver = new ManagerRecordingDriver();
        $manager = new QueueManager($driver);
        $job = new ManagerTestJob();
        $job->id = '42';
        $job->retryAfter = 120;

        $manager->release($job);

        $this->assertSame('42', $driver->releasedId);
        $this->assertSame(120, $driver->releasedDelay);
    }

    public function test_release_with_explicit_delay(): void
    {
        $driver = new ManagerRecordingDriver();
        $manager = new QueueManager($driver);
        $job = new ManagerTestJob();
        $job->id = '42';

        $manager->release($job, 30);

        $this->assertSame(30, $driver->releasedDelay);
    }

    public function test_size_delegates_to_driver(): void
    {
        $driver = new ManagerRecordingDriver();
        $driver->sizeResult = 5;
        $manager = new QueueManager($driver);

        $this->assertSame(5, $manager->size('default'));
    }

    public function test_clear_delegates_to_driver(): void
    {
        $driver = new ManagerRecordingDriver();
        $driver->clearResult = 3;
        $manager = new QueueManager($driver);

        $this->assertSame(3, $manager->clear('default'));
    }

    public function test_driver_returns_underlying_driver(): void
    {
        $driver = new SyncQueueDriver();
        $manager = new QueueManager($driver);

        $this->assertSame($driver, $manager->driver());
    }
}

class ManagerTestJob extends Job
{
    public bool $handled = false;
    public bool $failedCalled = false;

    public function handle(): void
    {
        $this->handled = true;
    }

    public function failed(\Throwable $exception): void
    {
        $this->failedCalled = true;
    }
}

class ManagerFailingJob extends Job
{
    public bool $failedCalled = false;

    public function handle(): void
    {
        throw new \RuntimeException('boom');
    }

    public function failed(\Throwable $exception): void
    {
        $this->failedCalled = true;
    }
}

class ManagerRecordingDriver implements QueueDriver
{
    public ?string $poppedQueue = null;
    public ?string $completedId = null;
    public ?string $failedId = null;
    public ?string $releasedId = null;
    public ?int $releasedDelay = null;
    public int $sizeResult = 0;
    public int $clearResult = 0;

    public function push(Job $job, string $queue = 'default', ?int $delay = null): string
    {
        return '1';
    }

    public function pop(string $queue = 'default'): ?Job
    {
        $this->poppedQueue = $queue;
        return null;
    }

    public function complete(string $jobId): void
    {
        $this->completedId = $jobId;
    }

    public function fail(string $jobId, \Throwable $exception): void
    {
        $this->failedId = $jobId;
    }

    public function release(string $jobId, int $delay = 0): void
    {
        $this->releasedId = $jobId;
        $this->releasedDelay = $delay;
    }

    public function size(?string $queue = null): int
    {
        return $this->sizeResult;
    }

    public function clear(?string $queue = null): int
    {
        return $this->clearResult;
    }
}
