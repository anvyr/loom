<?php

declare(strict_types=1);

namespace Anvyr\Loom\Contracts;

use Anvyr\Loom\Queue\Job;

interface QueueDriver
{
    /**
     * @throws \RuntimeException If serialization fails
     */
    public function push(Job $job, string $queue = 'default', ?int $delay = null): string;

    public function pop(string $queue = 'default'): ?Job;

    public function complete(string $jobId): void;

    public function fail(string $jobId, \Throwable $exception): void;

    public function release(string $jobId, int $delay = 0): void;

    public function size(?string $queue = null): int;

    public function clear(?string $queue = null): int;
}
