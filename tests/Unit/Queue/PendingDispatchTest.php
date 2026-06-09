<?php

declare(strict_types=1);

namespace Anvyr\Loom\Tests\Unit\Queue;

use Anvyr\Loom\Drivers\Queue\SyncQueueDriver;
use Anvyr\Loom\Queue\Job;
use Anvyr\Loom\Queue\PendingDispatch;
use Anvyr\Loom\Tests\Support\TestCase;

final class PendingDispatchTest extends TestCase
{
    public function test_send_pushes_job_via_driver(): void
    {
        $driver = new SyncQueueDriver();
        $job = new DispatchTestJob();
        $dispatch = new PendingDispatch($driver, $job);

        $id = $dispatch->send();

        $this->assertSame('1', $id);
        $this->assertTrue($job->handled);
    }

    public function test_on_queue_sets_queue(): void
    {
        $driver = new RecordingQueueDriver();
        $job = new DispatchTestJob();
        $dispatch = new PendingDispatch($driver, $job);

        $dispatch->onQueue('emails')->send();

        $this->assertSame('emails', $driver->lastQueue);
    }

    public function test_delay_with_seconds(): void
    {
        $driver = new RecordingQueueDriver();
        $job = new DispatchTestJob();
        $dispatch = new PendingDispatch($driver, $job);

        $dispatch->delay(seconds: 30)->send();

        $this->assertSame(30, $driver->lastDelay);
    }

    public function test_delay_with_minutes(): void
    {
        $driver = new RecordingQueueDriver();
        $job = new DispatchTestJob();
        $dispatch = new PendingDispatch($driver, $job);

        $dispatch->delay(minutes: 5)->send();

        $this->assertSame(300, $driver->lastDelay);
    }

    public function test_delay_with_datetime(): void
    {
        $driver = new RecordingQueueDriver();
        $job = new DispatchTestJob();
        $dispatch = new PendingDispatch($driver, $job);

        $future = new \DateTimeImmutable('+60 seconds');
        $dispatch->delay(until: $future)->send();

        $this->assertGreaterThanOrEqual(59, $driver->lastDelay);
        $this->assertLessThanOrEqual(61, $driver->lastDelay);
    }

    public function test_send_now_executes_inline(): void
    {
        $driver = new RecordingQueueDriver();
        $job = new DispatchTestJob();
        $dispatch = new PendingDispatch($driver, $job);

        $dispatch->sendNow();

        $this->assertTrue($job->handled);
        $this->assertStringStartsWith('sync-', $job->id);
        $this->assertSame(1, $job->attempts);
        // Driver should not have been called
        $this->assertNull($driver->lastQueue);
    }

    public function test_send_now_calls_failed_on_exception(): void
    {
        $driver = new RecordingQueueDriver();
        $job = new FailingDispatchTestJob();
        $dispatch = new PendingDispatch($driver, $job);

        try {
            $dispatch->sendNow();
        } catch (\RuntimeException) {
            // expected
        }

        $this->assertTrue($job->failedCalled);
    }

    public function test_inherits_job_queue_and_delay(): void
    {
        $driver = new RecordingQueueDriver();
        $job = new DispatchTestJob();
        $job->queue = 'reports';
        $job->delay = 45;

        $dispatch = new PendingDispatch($driver, $job);
        $dispatch->send();

        $this->assertSame('reports', $driver->lastQueue);
        $this->assertSame(45, $driver->lastDelay);
    }
}

class DispatchTestJob extends Job
{
    public bool $handled = false;

    public function handle(): void
    {
        $this->handled = true;
    }
}

class FailingDispatchTestJob extends Job
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

class RecordingQueueDriver implements \Anvyr\Loom\Contracts\QueueDriver
{
    public ?string $lastQueue = null;
    public ?int $lastDelay = null;
    public int $pushCount = 0;

    public function push(Job $job, string $queue = 'default', ?int $delay = null): string
    {
        $this->lastQueue = $queue;
        $this->lastDelay = $delay;
        $this->pushCount++;
        return (string) $this->pushCount;
    }

    public function pop(string $queue = 'default'): ?Job
    {
        return null;
    }
    public function complete(string $jobId): void
    {
    }
    public function fail(string $jobId, \Throwable $exception): void
    {
    }
    public function release(string $jobId, int $delay = 0): void
    {
    }
    public function size(?string $queue = null): int
    {
        return 0;
    }
    public function clear(?string $queue = null): int
    {
        return 0;
    }
}
