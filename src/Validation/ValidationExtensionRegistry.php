<?php

declare(strict_types=1);

namespace Anvyr\Loom\Validation;

use Closure;

final class ValidationExtensionRegistry
{
    /** @var array<string, Closure> */
    private array $extensions = [];

    public function extend(string $rule, Closure $callback): void
    {
        $this->extensions[$rule] = $callback;
    }

    public function has(string $rule): bool
    {
        return isset($this->extensions[$rule]);
    }

    public function get(string $rule): ?Closure
    {
        return $this->extensions[$rule] ?? null;
    }

    public function clear(): void
    {
        $this->extensions = [];
    }
}
