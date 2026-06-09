<?php

declare(strict_types=1);

namespace Anvyr\Loom\Queue;

final readonly class WorkerOptions
{
    public function __construct(
        public string $queue = 'default',
        public int $sleep = 3,
        public int $memoryLimit = 128,
        public int $maxJobs = 0,
        public int $timeout = 60,
        public int $retryAfter = 90,
    ) {
    }
}
