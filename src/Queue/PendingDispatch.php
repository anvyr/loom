<?php

declare(strict_types=1);

namespace Anvyr\Loom\Queue;

use Anvyr\Loom\Contracts\QueueDriver;

class PendingDispatch
{
    private ?int $delay = null;
    private string $queue = 'default';

    public function __construct(
        private readonly QueueDriver $driver,
        private readonly Job $job
    ) {
        $this->queue = $job->queue;
        $this->delay = $job->delay;
    }

    public function onQueue(string $queue): static
    {
        $this->queue = $queue;
        return $this;
    }

    public function delay(
        ?int $seconds = null,
        ?int $minutes = null,
        ?\DateTimeInterface $until = null
    ): static {
        if ($until !== null) {
            $this->delay = max(0, $until->getTimestamp() - time());
        } elseif ($minutes !== null) {
            $this->delay = $minutes * 60;
        } elseif ($seconds !== null) {
            $this->delay = $seconds;
        }

        return $this;
    }

    public function send(): string
    {
        return $this->driver->push($this->job, $this->queue, $this->delay);
    }

    public function sendNow(): void
    {
        $this->job->id = 'sync-' . bin2hex(random_bytes(4));
        $this->job->attempts = 1;

        try {
            $this->job->handle();
        } catch (\Throwable $e) {
            $this->job->failed($e);
            throw $e;
        }
    }
}
