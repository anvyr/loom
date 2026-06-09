<?php

declare(strict_types=1);

namespace Anvyr\Loom\Commands\Queue;

use Anvyr\Loom\Commands\Command;
use Anvyr\Loom\Queue\Worker;
use Anvyr\Loom\Queue\WorkerOptions;

final class WorkCommand extends Command
{
    public function __construct(
        private readonly Worker $worker,
    ) {
    }

    public function signature(): string
    {
        return 'queue:work [--queue=] [--sleep=] [--memory=] [--max-jobs=] [--timeout=] [--retry-after=]';
    }

    public function description(): string
    {
        return 'Process jobs from the queue';
    }

    public static function category(): string
    {
        return 'Queue';
    }

    public function handle(): int
    {
        $options = new WorkerOptions(
            queue: $this->option('queue', config('queue.default', 'default')),
            sleep: (int) $this->option('sleep', config('queue.sleep', 3)),
            memoryLimit: (int) $this->option('memory', config('queue.memory_limit', 128)),
            maxJobs: (int) $this->option('max-jobs', 0),
            timeout: (int) $this->option('timeout', 60),
            retryAfter: (int) $this->option('retry-after', config('queue.retry_after', 90)),
        );

        $this->info("Processing jobs on [{$options->queue}] queue...");

        $this->worker->daemon($options);

        return self::SUCCESS;
    }
}
