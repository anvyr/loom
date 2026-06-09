<?php

declare(strict_types=1);

namespace Anvyr\Loom\Queue;

use Anvyr\Loom\Core\EventDispatcher;
use Anvyr\Loom\Exceptions\JobTimeoutException;

final class Worker
{
    private bool $shouldQuit = false;

    public function __construct(
        private readonly QueueManager $manager,
        private readonly ?EventDispatcher $events = null,
    ) {
    }

    public function daemon(WorkerOptions $options): void
    {
        if ($this->supportsAsyncSignals()) {
            $this->listenForSignals();
            if (function_exists('pcntl_async_signals')) {
                pcntl_async_signals(true);
            }
        }

        $jobsProcessed = 0;

        while (true) {
            if ($this->supportsAsyncSignals()) {
                pcntl_signal_dispatch();
            }

            if ($this->shouldQuit) {
                return;
            }

            $job = $this->manager->pop($options->queue);

            if ($job !== null) {
                $this->process($job, $options);
                $jobsProcessed++;

                if ($options->maxJobs > 0 && $jobsProcessed >= $options->maxJobs) {
                    return;
                }
            } else {
                sleep($options->sleep);
            }

            if ($this->memoryExceeded($options->memoryLimit)) {
                $this->stop();
            }
        }
    }

    public function process(Job $job, WorkerOptions $options): void
    {
        $this->dispatchEvent('queue.job.processing', $job);

        try {
            $this->runJobHandleWithTimeout($job, $options);

            $this->manager->complete($job);
            $this->dispatchEvent('queue.job.processed', $job);
        } catch (\Throwable $e) {
            $this->handleException($job, $e);
        }
    }

    private function handleException(Job $job, \Throwable $e): void
    {
        if ($job->attempts >= $job->tries) {
            $this->manager->fail($job, $e);
            $this->dispatchEvent('queue.job.failed', ['job' => $job, 'exception' => $e]);
        } else {
            $this->manager->release($job, $job->retryAfter);
            $this->dispatchEvent('queue.job.released', $job);
        }
    }

    private function dispatchEvent(string $event, mixed $payload): void
    {
        $this->events?->dispatch($event, $payload);
    }

    private function supportsAsyncSignals(): bool
    {
        return extension_loaded('pcntl');
    }

    private function supportsJobTimeout(): bool
    {
        return extension_loaded('pcntl')
            && function_exists('pcntl_alarm')
            && function_exists('pcntl_signal')
            && function_exists('pcntl_async_signals')
            && defined('SIGALRM');
    }

    private function runJobHandleWithTimeout(Job $job, WorkerOptions $options): void
    {
        $timeoutSeconds = $this->resolveJobTimeoutSeconds($job, $options);
        if ($timeoutSeconds <= 0 || !$this->supportsJobTimeout()) {
            $job->handle();

            return;
        }

        $previousAsyncSignals = pcntl_async_signals();
        $previousAlarmHandler = function_exists('pcntl_signal_get_handler')
            ? pcntl_signal_get_handler(SIGALRM)
            : SIG_DFL;

        pcntl_async_signals(true);
        $handlerOk = pcntl_signal(SIGALRM, function () use ($timeoutSeconds): never {
            pcntl_alarm(0);
            throw new JobTimeoutException("Job timed out after {$timeoutSeconds} seconds.");
        });

        if ($handlerOk !== true) {
            pcntl_async_signals($previousAsyncSignals);
            $job->handle();

            return;
        }

        pcntl_alarm($timeoutSeconds);
        try {
            $job->handle();
        } finally {
            pcntl_alarm(0);
            $this->restoreSignalHandler(SIGALRM, $previousAlarmHandler);
            pcntl_async_signals($previousAsyncSignals);
        }
    }

    private function restoreSignalHandler(int $signal, mixed $handler): void
    {
        if (is_int($handler) || is_callable($handler)) {
            pcntl_signal($signal, $handler);

            return;
        }

        pcntl_signal($signal, SIG_DFL);
    }

    private function resolveJobTimeoutSeconds(Job $job, WorkerOptions $options): int
    {
        $workerCap = $options->timeout;
        $jobLimit = $job->timeout;

        if ($workerCap > 0 && $jobLimit > 0) {
            return min($workerCap, $jobLimit);
        }

        if ($workerCap > 0) {
            return $workerCap;
        }

        if ($jobLimit > 0) {
            return $jobLimit;
        }

        return 0;
    }

    private function listenForSignals(): void
    {
        pcntl_signal(SIGTERM, fn () => $this->stop());
        pcntl_signal(SIGINT, fn () => $this->stop());
    }

    private function memoryExceeded(int $memoryLimit): bool
    {
        return (memory_get_usage(true) / 1024 / 1024) >= $memoryLimit;
    }

    public function stop(): void
    {
        $this->shouldQuit = true;
    }
}
