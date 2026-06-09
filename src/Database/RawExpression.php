<?php

declare(strict_types=1);

namespace Anvyr\Loom\Database;

/**
 * Raw SQL expression.
 * Never pass user input directly; use parameters for user data.
 */
final class RawExpression
{
    /** @param list<mixed> $bindings */
    public function __construct(
        private readonly string $expression,
        private readonly array $bindings = []
    ) {
    }

    public function getValue(): string
    {
        return $this->expression;
    }

    /** @return list<mixed> */
    public function getBindings(): array
    {
        return $this->bindings;
    }

    public function __toString(): string
    {
        return $this->expression;
    }
}
