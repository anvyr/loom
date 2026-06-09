<?php

declare(strict_types=1);

namespace Anvyr\Loom\Contracts;

use Anvyr\Loom\Database\Connection;

interface ModelInterface
{
    public function getTable(): string;

    public function getKey(): int|string|null;

    public function getKeyName(): string;

    /** @return array<string, mixed> */
    public function getAttributes(): array;

    public function isNew(): bool;

    public function exists(): bool;

    public function getConnection(): Connection;
}
