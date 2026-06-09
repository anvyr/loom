<?php

declare(strict_types=1);

namespace Anvyr\Loom\Drivers\Queue;

use Anvyr\Loom\Contracts\QueueDriver;
use Anvyr\Loom\Queue\Job;

class SyncQueueDriver implements QueueDriver
{
    private int $nextId = 1;

    public function push(Job $job, string $queue = 'default', ?int $delay = null): string
    {
        $id = (string) $this->nextId++;
        $job->id = $id;
        $job->attempts = 1;

        try {
            $job->handle();
        } catch (\Throwable $e) {
            $job->failed($e);
            throw $e;
        }

        return $id;
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
