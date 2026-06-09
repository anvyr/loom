<?php

declare(strict_types=1);

namespace Anvyr\Loom\Drivers\Queue;

use Anvyr\Loom\Contracts\QueueDriver;
use Anvyr\Loom\Contracts\ShouldBeUnique;
use Anvyr\Loom\Database\Connection;
use Anvyr\Loom\Queue\Job;

class DatabaseQueueDriver implements QueueDriver
{
    private string $table;
    private string $failedTable;

    public function __construct(
        private readonly Connection $db
    ) {
        $this->table = config('queue.table', 'jobs');
        $this->failedTable = config('queue.failed_table', 'failed_jobs');
    }

    public function push(Job $job, string $queue = 'default', ?int $delay = null): string
    {
        $payload = json_encode($job->serialize(), JSON_THROW_ON_ERROR);
        $availableAt = date('Y-m-d H:i:s', time() + ($delay ?? 0));
        $now = date('Y-m-d H:i:s');

        if ($job instanceof ShouldBeUnique) {
            return $this->pushUnique($job, $queue, $payload, $availableAt, $now);
        }

        $this->db->statement(
            "INSERT INTO {$this->table} (queue, payload, attempts, available_at, created_at) VALUES (?, ?, 0, ?, ?)",
            [$queue, $payload, $availableAt, $now]
        );

        return $this->db->lastInsertId();
    }

    private function pushUnique(ShouldBeUnique&Job $job, string $queue, string $payload, string $availableAt, string $now): string
    {
        $uniqueId = $job->uniqueId();
        $driver = $this->db->getDriver();

        if ($driver === 'sqlite') {
            $this->db->statement(
                "INSERT OR IGNORE INTO {$this->table} (queue, payload, attempts, unique_id, available_at, created_at) VALUES (?, ?, 0, ?, ?, ?)",
                [$queue, $payload, $uniqueId, $availableAt, $now]
            );
        } elseif ($driver === 'pgsql') {
            $this->db->statement(
                "INSERT INTO {$this->table} (queue, payload, attempts, unique_id, available_at, created_at) VALUES (?, ?, 0, ?, ?, ?) ON CONFLICT (unique_id) DO NOTHING",
                [$queue, $payload, $uniqueId, $availableAt, $now]
            );
        } else {
            // MySQL / MariaDB
            $this->db->statement(
                "INSERT INTO {$this->table} (queue, payload, attempts, unique_id, available_at, created_at) VALUES (?, ?, 0, ?, ?, ?) ON DUPLICATE KEY UPDATE id = id",
                [$queue, $payload, $uniqueId, $availableAt, $now]
            );
        }

        // Return the existing or newly inserted row's id
        $row = $this->db->query(
            "SELECT id FROM {$this->table} WHERE unique_id = ? AND queue = ? LIMIT 1",
            [$uniqueId, $queue]
        );

        return (string) $row[0]['id'];
    }

    public function pop(string $queue = 'default'): ?Job
    {
        $now = date('Y-m-d H:i:s');
        $retryAfter = (int) config('queue.retry_after', 90);
        $threshold = date('Y-m-d H:i:s', time() - $retryAfter);

        // Reclaim stuck jobs: release reservations older than retry_after threshold
        $this->db->statement(
            "UPDATE {$this->table} SET reserved_at = NULL, available_at = ? WHERE queue = ? AND reserved_at IS NOT NULL AND reserved_at <= ?",
            [$now, $queue, $threshold]
        );

        $driver = $this->db->getDriver();

        if ($driver === 'sqlite') {
            return $this->popSqlite($queue, $now);
        }

        return $this->popStandard($queue, $now);
    }

    /**
     * MySQL/PostgreSQL: SELECT FOR UPDATE to atomically claim a job.
     */
    private function popStandard(string $queue, string $now): ?Job
    {
        return $this->db->transaction(function () use ($queue, $now) {
            $candidates = $this->db->query(
                "SELECT id FROM {$this->table} WHERE queue = ? AND available_at <= ? AND reserved_at IS NULL ORDER BY id ASC LIMIT 1 FOR UPDATE SKIP LOCKED",
                [$queue, $now]
            );

            if (empty($candidates)) {
                return null;
            }

            $candidateId = $candidates[0]['id'];

            $this->db->statement(
                "UPDATE {$this->table} SET reserved_at = ?, attempts = attempts + 1 WHERE id = ?",
                [$now, $candidateId]
            );

            $record = $this->db->query(
                "SELECT * FROM {$this->table} WHERE id = ?",
                [$candidateId]
            );

            if (empty($record)) {
                return null;
            }

            return $this->hydrateJob($record[0], $queue);
        });
    }

    /**
     * SQLite: two-step claim in transaction with optimistic guard.
     */
    private function popSqlite(string $queue, string $now): ?Job
    {
        return $this->db->transaction(function () use ($queue, $now) {
            $candidates = $this->db->query(
                "SELECT id FROM {$this->table} WHERE queue = ? AND available_at <= ? AND reserved_at IS NULL ORDER BY id ASC LIMIT 1",
                [$queue, $now]
            );

            if (empty($candidates)) {
                return null;
            }

            $candidateId = $candidates[0]['id'];

            // Guard: only claim if still unreserved
            $affected = $this->db->statement(
                "UPDATE {$this->table} SET reserved_at = ?, attempts = attempts + 1 WHERE id = ? AND reserved_at IS NULL",
                [$now, $candidateId]
            );

            if ($affected === 0) {
                return null;
            }

            $record = $this->db->query(
                "SELECT * FROM {$this->table} WHERE id = ?",
                [$candidateId]
            );

            if (empty($record)) {
                return null;
            }

            return $this->hydrateJob($record[0], $queue);
        });
    }

    /** @param array<string, mixed> $record */
    private function hydrateJob(array $record, string $queue): Job
    {
        $payload = json_decode($record['payload'], true, 512, JSON_THROW_ON_ERROR);
        $job = Job::deserialize($payload);
        $job->id = (string) $record['id'];
        $job->attempts = (int) $record['attempts'];
        $job->queue = $queue;

        return $job;
    }

    public function complete(string $jobId): void
    {
        $this->db->statement(
            "DELETE FROM {$this->table} WHERE id = ?",
            [(int) $jobId]
        );
    }

    public function fail(string $jobId, \Throwable $exception): void
    {
        $record = $this->db->query(
            "SELECT * FROM {$this->table} WHERE id = ?",
            [(int) $jobId]
        );

        if (empty($record)) {
            return;
        }

        $row = $record[0];

        $this->db->statement(
            "INSERT INTO {$this->failedTable} (queue, payload, exception, failed_at) VALUES (?, ?, ?, ?)",
            [$row['queue'], $row['payload'], $this->formatException($exception), date('Y-m-d H:i:s')]
        );

        $this->db->statement(
            "DELETE FROM {$this->table} WHERE id = ?",
            [(int) $jobId]
        );
    }

    public function release(string $jobId, int $delay = 0): void
    {
        $availableAt = date('Y-m-d H:i:s', time() + $delay);

        $this->db->statement(
            "UPDATE {$this->table} SET reserved_at = NULL, available_at = ? WHERE id = ?",
            [$availableAt, (int) $jobId]
        );
    }

    public function size(?string $queue = null): int
    {
        if ($queue !== null) {
            $result = $this->db->query(
                "SELECT COUNT(*) as cnt FROM {$this->table} WHERE queue = ?",
                [$queue]
            );
        } else {
            $result = $this->db->query("SELECT COUNT(*) as cnt FROM {$this->table}");
        }

        return (int) ($result[0]['cnt'] ?? 0);
    }

    public function clear(?string $queue = null): int
    {
        if ($queue !== null) {
            return $this->db->statement(
                "DELETE FROM {$this->table} WHERE queue = ?",
                [$queue]
            );
        }

        return $this->db->statement("DELETE FROM {$this->table}");
    }

    private function formatException(\Throwable $e): string
    {
        return sprintf(
            "[%s] %s in %s:%d\n%s",
            get_class($e),
            $e->getMessage(),
            $e->getFile(),
            $e->getLine(),
            $e->getTraceAsString()
        );
    }
}
