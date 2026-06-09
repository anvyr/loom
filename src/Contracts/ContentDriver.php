<?php

declare(strict_types=1);

namespace Anvyr\Loom\Contracts;

use Anvyr\Loom\Database\Collection;
use Anvyr\Loom\Models\Page;

interface ContentDriver
{
    /** @throws \Anvyr\Loom\Exceptions\NotFoundException */
    public function load(string $slug): Page;

    /** @throws \Anvyr\Loom\Exceptions\ValidationException */
    public function save(Page $page): bool;

    /**
     * @param array<string, mixed> $filters
     * @return Collection<Page>
     */
    public function list(array $filters = []): Collection;

    /**
     * @param array<string, mixed> $filters
     * @return Collection<Page>
     */
    public function paginate(int $page = 1, int $perPage = 20, array $filters = []): Collection;

    /** @throws \Anvyr\Loom\Exceptions\NotFoundException */
    public function delete(string $slug): bool;

    public function exists(string $slug): bool;

    /** @param array<string, mixed> $filters */
    public function count(array $filters = []): int;

    /** Return the last-modified timestamp for a page, or null if it does not exist. */
    public function lastModified(string $slug): ?int;
}
