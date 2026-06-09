<?php

declare(strict_types=1);

namespace Anvyr\Loom\Content\Index;

interface PageIndex
{
    public function get(string $slug): ?PageIndexEntry;

    public function getById(string $id): ?PageIndexEntry;

    public function put(PageIndexEntry $entry): void;

    public function delete(string $slug): void;

    /**
     * @return list<PageIndexEntry>
     */
    public function query(?PageIndexQuery $query = null): array;

    public function count(?PageIndexQuery $query = null): int;

    /**
     * @param iterable<string, string> $filesBySlug
     */
    public function sync(iterable $filesBySlug, PageIndexer $indexer): void;

    /**
     * @param iterable<string, string> $filesBySlug
     */
    public function rebuild(iterable $filesBySlug, PageIndexer $indexer): void;
}
