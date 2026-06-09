<?php

declare(strict_types=1);

namespace Anvyr\Loom\Tests\Unit\Queue;

use Anvyr\Loom\Contracts\QueueDriver;
use Anvyr\Loom\Core\EventDispatcher;
use Anvyr\Loom\Exceptions\JobTimeoutException;
use Anvyr\Loom\Queue\Job;
use Anvyr\Loom\Queue\QueueManager;
use Anvyr\Loom\Queue\Worker;
use Anvyr\Loom\Queue\WorkerOptions;
use Anvyr\Loom\Tests\Support\TestCase;

final class WorkerTest extends TestCase
{
    public function test_process_completes_successful_job(): void
    {
        $driver = new WorkerTestDriver();
        $manager = new QueueManager($driver);
        $worker = new Worker($manager);
        $options = new WorkerOptions();

        $job = new WorkerTestJob();
        $job->id = '1';

        $worker->process($job, $options);

        $this->assertTrue($job->handled);
        $this->assertSame('1', $driver->completedId);
    }

    public function test_process_dispatches_events(): void
    {
        $events = new EventDispatcher();
        $driver = new WorkerTestDriver();
        $manager = new QueueManager($driver);
        $worker = new Worker($manager, $events);
        $options = new WorkerOptions();

        $dispatched = [];
        $events->listen('queue.job.processing', function ($payload) use (&$dispatched) {
            $dispatched[] = 'processing';
        });
        $events->listen('queue.job.processed', function ($payload) use (&$dispatched) {
            $dispatched[] = 'processed';
        });

        $job = new WorkerTestJob();
        $job->id = '1';

        $worker->process($job, $options);

        $this->assertSame(['processing', 'processed'], $dispatched);
    }

    public function test_process_fails_permanently_when_attempts_exceed_tries(): void
    {
        $driver = new WorkerTestDriver();
        $manager = new QueueManager($driver);
        $worker = new Worker($manager);
        $options = new WorkerOptions();

        $job = new FailingWorkerTestJob();
        $job->id = '1';
        $job->tries = 3;
        $job->attempts = 3;

        $worker->process($job, $options);

        $this->assertSame('1', $driver->failedId);
        $this->assertNull($driver->releasedId);
        $this->assertTrue($job->failedCalled);
    }

    public function test_process_releases_for_retry_when_attempts_below_tries(): void
    {
        $driver = new WorkerTestDriver();
        $manager = new QueueManager($driver);
        $worker = new Worker($manager);
        $options = new WorkerOptions();

        $job = new FailingWorkerTestJob();
        $job->id = '1';
        $job->tries = 3;
        $job->attempts = 1;
        $job->retryAfter = 60;

        $worker->process($job, $options);

        $this->assertSame('1', $driver->releasedId);
        $this->assertSame(60, $driver->releasedDelay);
        $this->assertNull($driver->failedId);
    }

    public function test_daemon_stops_after_max_jobs(): void
    {
        $job1 = new WorkerTestJob();
        $job1->id = '1';
        $job2 = new WorkerTestJob();
        $job2->id = '2';

        $driver = new WorkerTestDriver();
        $driver->jobsToReturn = [$job1, $job2, null];
        $manager = new QueueManager($driver);
        $worker = new Worker($manager);

        $options = new WorkerOptions(maxJobs: 2, sleep: 0);

        $worker->daemon($options);

        $this->assertTrue($job1->handled);
        $this->assertTrue($job2->handled);
    }

    public function test_daemon_can_be_stopped(): void
    {
        $driver = new WorkerTestDriver();
        $driver->jobsToReturn = [null];
        $manager = new QueueManager($driver);
        $worker = new Worker($manager);

        $options = new WorkerOptions(maxJobs: 1, sleep: 0);

        // stop() before daemon has a chance to process
        $worker->stop();
        $worker->daemon($options);

        $this->addToAssertionCount(1);
    }

    public function test_process_fails_with_timeout_when_job_exceeds_worker_cap(): void
    {
        if (!$this->pcntlSupportsJobTimeout()) {
            $this->markTestSkipped('pcntl SIGALRM job timeout not available');
        }

        $driver = new WorkerTestDriver();
        $manager = new QueueManager($driver);
        $worker = new Worker($manager);
        $options = new WorkerOptions(timeout: 1);

        $job = new SlowWorkerTestJob(5);
        $job->id = '1';
        $job->tries = 3;
        $job->attempts = 3;

        $worker->process($job, $options);

        $this->assertFalse($job->completed);
        $this->assertSame('1', $driver->failedId);
        $this->assertInstanceOf(JobTimeoutException::class, $driver->failedThrowable);
    }

    public function test_process_respects_least_of_worker_and_job_timeout(): void
    {
        if (!$this->pcntlSupportsJobTimeout()) {
            $this->markTestSkipped('pcntl SIGALRM job timeout not available');
        }

        $driver = new WorkerTestDriver();
        $manager = new QueueManager($driver);
        $worker = new Worker($manager);
        $options = new WorkerOptions(timeout: 60);

        $job = new SlowWorkerTestJob(5);
        $job->id = '1';
        $job->tries = 3;
        $job->attempts = 3;
        $job->timeout = 1;

        $worker->process($job, $options);

        $this->assertFalse($job->completed);
        $this->assertSame('1', $driver->failedId);
        $this->assertInstanceOf(JobTimeoutException::class, $driver->failedThrowable);
    }

    private function pcntlSupportsJobTimeout(): bool
    {
        return extension_loaded('pcntl')
            && function_exists('pcntl_alarm')
            && function_exists('pcntl_signal')
            && function_exists('pcntl_async_signals')
            && defined('SIGALRM');
    }
}

class WorkerTestJob extends Job
{
    public bool $handled = false;

    public function handle(): void
    {
        $this->handled = true;
    }
}

class FailingWorkerTestJob extends Job
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

class SlowWorkerTestJob extends Job
{
    public bool $completed = false;

    public function __construct(private int $sleepSeconds)
    {
    }

    public function handle(): void
    {
        sleep($this->sleepSeconds);
        $this->completed = true;
    }
}

class WorkerTestDriver implements QueueDriver
{
    public ?string $completedId = null;
    public ?string $failedId = null;
    public ?\Throwable $failedThrowable = null;
    public ?string $releasedId = null;
    public ?int $releasedDelay = null;

    /** @var list<Job|null> */
    public array $jobsToReturn = [];
    private int $popIndex = 0;

    public function push(Job $job, string $queue = 'default', ?int $delay = null): string
    {
        return '1';
    }

    public function pop(string $queue = 'default'): ?Job
    {
        return $this->jobsToReturn[$this->popIndex++] ?? null;
    }

    public function complete(string $jobId): void
    {
        $this->completedId = $jobId;
    }

    public function fail(string $jobId, \Throwable $exception): void
    {
        $this->failedId = $jobId;
        $this->failedThrowable = $exception;
    }

    public function release(string $jobId, int $delay = 0): void
    {
        $this->releasedId = $jobId;
        $this->releasedDelay = $delay;
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
