<?php

declare(strict_types=1);

/**
 * Queue Configuration
 */
return [
    /** Queue driver: database, sync */
    'driver' => env('QUEUE_DRIVER', 'database'),

    /** Table names */
    'table' => 'jobs',
    'failed_table' => 'failed_jobs',
    'batches_table' => 'job_batches',

    /** Seconds before a reserved job is considered stuck and reclaimed */
    'retry_after' => 90,

    /** Default queue name */
    'default' => 'default',

    /** Worker: seconds to sleep when no jobs are available */
    'sleep' => 3,

    /** Worker: memory limit in MB (daemon restarts when exceeded) */
    'memory_limit' => 128,
];
