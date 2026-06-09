<?php

declare(strict_types=1);

namespace Anvyr\Loom\Contracts;

interface DataStore
{
    /** @return array<string, mixed>|null */
    public function get(string $collection, string $key): ?array;

    /** @param array<string, mixed> $data */
    public function put(string $collection, string $key, array $data): void;

    public function forget(string $collection, string $key): bool;

    public function has(string $collection, string $key): bool;

    /** @return array<string, array<string, mixed>> */
    public function all(string $collection): array;

    /**
     * @param callable(array<string, mixed>): bool $predicate
     * @return array<string, array<string, mixed>>
     */
    public function filter(string $collection, callable $predicate): array;

    public function clear(string $collection): void;

    public function driver(): string;
}
