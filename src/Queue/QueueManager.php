<?php

declare(strict_types=1);

namespace Anvyr\Loom\Queue;

use Anvyr\Loom\Contracts\QueueDriver;

final class QueueManager
{
    public function __construct(
        private readonly QueueDriver $driver,
    ) {
    }

    public function dispatch(Job $job): PendingDispatch
    {
        return new PendingDispatch($this->driver, $job);
    }

    public function dispatchNow(Job $job): void
    {
        $job->id = 'sync-' . bin2hex(random_bytes(4));
        $job->attempts = 1;

        try {
            $job->handle();
        } catch (\Throwable $e) {
            $job->failed($e);
            throw $e;
        }
    }

    public function pop(string $queue = 'default'): ?Job
    {
        return $this->driver->pop($queue);
    }

    public function complete(Job $job): void
    {
        if ($job->id !== null) {
            $this->driver->complete($job->id);
        }
    }

    public function fail(Job $job, \Throwable $exception): void
    {
        if ($job->id !== null) {
            $this->driver->fail($job->id, $exception);
        }
        $job->failed($exception);
    }

    public function release(Job $job, int $delay = 0): void
    {
        if ($job->id !== null) {
            $this->driver->release($job->id, $delay !== 0 ? $delay : $job->retryAfter);
        }
    }

    public function size(?string $queue = null): int
    {
        return $this->driver->size($queue);
    }

    public function clear(?string $queue = null): int
    {
        return $this->driver->clear($queue);
    }

    public function driver(): QueueDriver
    {
        return $this->driver;
    }
}
