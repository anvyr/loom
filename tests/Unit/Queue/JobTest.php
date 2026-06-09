<?php

declare(strict_types=1);

namespace Anvyr\Loom\Tests\Unit\Queue;

use Anvyr\Loom\Queue\Job;
use Anvyr\Loom\Tests\Support\TestCase;

final class JobTest extends TestCase
{
    public function test_defaults(): void
    {
        $job = new DummyJob();

        $this->assertSame(3, $job->tries);
        $this->assertSame(60, $job->retryAfter);
        $this->assertSame(60, $job->timeout);
        $this->assertNull($job->id);
        $this->assertSame(0, $job->attempts);
        $this->assertSame('default', $job->queue);
        $this->assertNull($job->delay);
    }

    public function test_on_queue(): void
    {
        $job = new DummyJob();
        $result = $job->onQueue('emails');

        $this->assertSame($job, $result);
        $this->assertSame('emails', $job->queue);
    }

    public function test_delay_with_seconds(): void
    {
        $job = new DummyJob();
        $job->delay(seconds: 30);

        $this->assertSame(30, $job->delay);
    }

    public function test_delay_with_datetime(): void
    {
        $job = new DummyJob();
        $future = new \DateTimeImmutable('+60 seconds');
        $job->delay(until: $future);

        $this->assertGreaterThanOrEqual(59, $job->delay);
        $this->assertLessThanOrEqual(61, $job->delay);
    }

    public function test_failed_is_callable(): void
    {
        $job = new DummyJob();
        $job->failed(new \RuntimeException('test'));

        $this->addToAssertionCount(1);
    }

    public function test_serialize_and_deserialize(): void
    {
        $job = new DummyJobWithData('hello', 42);
        $job->tries = 5;
        $job->retryAfter = 120;
        $job->timeout = 30;

        $payload = $job->serialize();

        $this->assertSame(DummyJobWithData::class, $payload['class']);
        $this->assertSame('hello', $payload['data']['message']);
        $this->assertSame(42, $payload['data']['count']);
        $this->assertSame(5, $payload['meta']['tries']);
        $this->assertSame(120, $payload['meta']['retryAfter']);
        $this->assertSame(30, $payload['meta']['timeout']);

        // Meta keys should not be in data
        $this->assertArrayNotHasKey('tries', $payload['data']);
        $this->assertArrayNotHasKey('retryAfter', $payload['data']);
        $this->assertArrayNotHasKey('id', $payload['data']);
        $this->assertArrayNotHasKey('attempts', $payload['data']);
    }

    public function test_deserialize_restores_job(): void
    {
        $original = new DummyJobWithData('world', 99);
        $original->tries = 7;

        $payload = $original->serialize();
        $restored = Job::deserialize($payload);

        $this->assertInstanceOf(DummyJobWithData::class, $restored);
        $this->assertSame('world', $restored->message);
        $this->assertSame(99, $restored->count);
        $this->assertSame(7, $restored->tries);
    }

    public function test_serialize_excludes_non_serializable_values(): void
    {
        $job = new DummyJobWithResource();
        $payload = $job->serialize();

        $this->assertArrayNotHasKey('resource', $payload['data']);
    }

    public function test_deserialize_with_default_constructor_params(): void
    {
        $job = new DummyJobWithDefaults();
        $payload = $job->serialize();
        $restored = Job::deserialize($payload);

        $this->assertInstanceOf(DummyJobWithDefaults::class, $restored);
        $this->assertSame('default', $restored->value);
    }
}

class DummyJob extends Job
{
    public function handle(): void
    {
    }
}

class DummyJobWithData extends Job
{
    public function __construct(
        public string $message = '',
        public int $count = 0,
    ) {
    }

    public function handle(): void
    {
    }
}

class DummyJobWithResource extends Job
{
    public object $resource;

    public function __construct()
    {
        $this->resource = new \stdClass();
    }

    public function handle(): void
    {
    }
}

class DummyJobWithDefaults extends Job
{
    public function __construct(
        public string $value = 'default',
    ) {
    }

    public function handle(): void
    {
    }
}
