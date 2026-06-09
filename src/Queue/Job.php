<?php

declare(strict_types=1);

namespace Anvyr\Loom\Queue;

// Store only IDs, not full models — fetch fresh data in handle().
abstract class Job
{
    public int $tries = 3;
    public int $retryAfter = 60;
    public int $timeout = 60;
    public ?string $id = null;
    public int $attempts = 0;
    public string $queue = 'default';
    public ?int $delay = null;

    abstract public function handle(): void;

    public function failed(\Throwable $exception): void
    {
    }

    public function onQueue(string $queue): static
    {
        $this->queue = $queue;
        return $this;
    }

    public function delay(?int $seconds = null, ?\DateTimeInterface $until = null): static
    {
        if ($until !== null) {
            $this->delay = max(0, $until->getTimestamp() - time());
        } elseif ($seconds !== null) {
            $this->delay = $seconds;
        }

        return $this;
    }

    /** @return array{class: class-string, data: array<string, mixed>, meta: array<string, mixed>} */
    public function serialize(): array
    {
        $reflection = new \ReflectionClass($this);
        $data = [];
        $metaKeys = ['id', 'attempts', 'queue', 'delay', 'tries', 'retryAfter', 'timeout'];

        foreach ($reflection->getProperties() as $property) {
            $name = $property->getName();
            if (in_array($name, $metaKeys, true)) {
                continue;
            }
            $value = $property->getValue($this);
            if (is_scalar($value) || is_array($value) || is_null($value)) {
                $data[$name] = $value;
            }
        }

        return [
            'class' => static::class,
            'data' => $data,
            'meta' => [
                'tries' => $this->tries,
                'retryAfter' => $this->retryAfter,
                'timeout' => $this->timeout,
            ],
        ];
    }

    /** @param array{class: class-string, data: array<string, mixed>, meta: array<string, mixed>} $payload */
    public static function deserialize(array $payload): static
    {
        $class = $payload['class'];
        $data = $payload['data'];
        $meta = $payload['meta'];

        $reflection = new \ReflectionClass($class);
        $constructor = $reflection->getConstructor();

        if ($constructor === null) {
            /** @var static $instance */
            $instance = new $class();
        } else {
            $args = [];
            foreach ($constructor->getParameters() as $param) {
                $name = $param->getName();
                if (array_key_exists($name, $data)) {
                    $args[] = $data[$name];
                    unset($data[$name]);
                } elseif ($param->isDefaultValueAvailable()) {
                    $args[] = $param->getDefaultValue();
                } else {
                    $args[] = null;
                }
            }
            /** @var static $instance */
            $instance = new $class(...$args);
        }

        foreach ($data as $name => $value) {
            if (property_exists($instance, $name)) {
                $property = $reflection->getProperty($name);
                $property->setValue($instance, $value);
            }
        }

        $instance->tries = $meta['tries'] ?? 3;
        $instance->retryAfter = $meta['retryAfter'] ?? 60;
        $instance->timeout = $meta['timeout'] ?? 60;

        return $instance;
    }
}
