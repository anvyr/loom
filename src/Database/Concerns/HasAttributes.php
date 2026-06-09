<?php

declare(strict_types=1);

namespace Anvyr\Loom\Database\Concerns;

use Anvyr\Loom\Support\Str;
use DateTimeImmutable;
use DateTimeInterface;

/**
 * Manages attribute storage, casting, dirty tracking, and accessor/mutator pipelines.
 *
 * @property array<string, mixed> $attributes
 * @property array<string, mixed> $original
 */
trait HasAttributes
{
    /** @var array<string, mixed> */
    protected array $attributes = [];

    /** @var array<string, mixed> */
    protected array $original = [];

    /** @var array<string, string> */
    protected array $casts = [];

    public function getAttribute(string $key): mixed
    {
        if ($this->hasGetMutator($key)) {
            $method = 'get' . Str::studly($key) . 'Attribute';
            return $this->{$method}($this->attributes[$key] ?? null);
        }

        $value = $this->attributes[$key] ?? null;

        if ($this->hasCast($key)) {
            return $this->castAttribute($key, $value);
        }

        return $value;
    }

    public function setAttribute(string $key, mixed $value): void
    {
        if ($this->hasSetMutator($key)) {
            $method = 'set' . Str::studly($key) . 'Attribute';
            $this->{$method}($value);
            return;
        }

        $this->attributes[$key] = $value;
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public function setRawAttributes(array $attributes, bool $sync = false): static
    {
        $this->attributes = $attributes;

        if ($sync) {
            $this->syncOriginal();
        }

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function attributesToArray(): array
    {
        $attributes = $this->attributes;
        $result = [];

        foreach ($attributes as $key => $value) {
            if ($this->hasGetMutator($key)) {
                $method = 'get' . Str::studly($key) . 'Attribute';
                $result[$key] = $this->{$method}($value);
            } elseif ($this->hasCast($key)) {
                $result[$key] = $this->castAttribute($key, $value);
            } else {
                $result[$key] = $value;
            }
        }

        return $result;
    }

    public function syncOriginal(): static
    {
        $this->original = $this->attributes;
        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function getDirty(): array
    {
        $dirty = [];

        foreach ($this->attributes as $key => $value) {
            if (!array_key_exists($key, $this->original) || $value !== $this->original[$key]) {
                $dirty[$key] = $value;
            }
        }

        return $dirty;
    }

    public function isDirty(?string $key = null): bool
    {
        if ($key !== null) {
            return array_key_exists($key, $this->attributes)
                && (!array_key_exists($key, $this->original) || $this->attributes[$key] !== $this->original[$key]);
        }

        return $this->getDirty() !== [];
    }

    public function isClean(?string $key = null): bool
    {
        return !$this->isDirty($key);
    }

    public function getOriginal(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->original;
        }

        return $this->original[$key] ?? $default;
    }

    protected function castAttribute(string $key, mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        return match ($this->casts[$key]) {
            'int', 'integer' => (int) $value,
            'float', 'double' => (float) $value,
            'bool', 'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'string' => (string) $value,
            'json', 'array' => is_string($value) ? json_decode($value, true) : $value,
            'datetime' => $value instanceof DateTimeInterface ? $value : $this->parseDateTime($value),
            'date' => $value instanceof DateTimeInterface ? $value : $this->parseDate($value),
            default => $value,
        };
    }

    protected function hasCast(string $key): bool
    {
        return array_key_exists($key, $this->casts);
    }

    protected function hasGetMutator(string $key): bool
    {
        return method_exists($this, 'get' . Str::studly($key) . 'Attribute');
    }

    protected function hasSetMutator(string $key): bool
    {
        return method_exists($this, 'set' . Str::studly($key) . 'Attribute');
    }

    private function parseDateTime(mixed $value): ?DateTimeImmutable
    {
        if ($value instanceof DateTimeImmutable) {
            return $value;
        }

        if ($value instanceof DateTimeInterface) {
            return DateTimeImmutable::createFromInterface($value);
        }

        try {
            return new DateTimeImmutable((string) $value);
        } catch (\Exception) {
            return null;
        }
    }

    private function parseDate(mixed $value): ?DateTimeImmutable
    {
        if ($value instanceof DateTimeImmutable) {
            return $value;
        }

        if ($value instanceof DateTimeInterface) {
            return DateTimeImmutable::createFromInterface($value);
        }

        $parsed = DateTimeImmutable::createFromFormat('Y-m-d', (string) $value);
        return $parsed !== false ? $parsed : null;
    }

    protected function initializeHasAttributes(): void
    {
    }
}
