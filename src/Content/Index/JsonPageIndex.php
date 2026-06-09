<?php

declare(strict_types=1);

namespace Anvyr\Loom\Content\Index;

final class JsonPageIndex implements PageIndex
{
    /** @var array<string, PageIndexEntry> */
    private array $entries = [];
    private bool $loaded = false;
    private bool $dirty = false;

    public function __construct(
        private readonly string $path,
    ) {
    }

    public function get(string $slug): ?PageIndexEntry
    {
        $this->load();

        return $this->entries[$slug] ?? null;
    }

    public function getById(string $id): ?PageIndexEntry
    {
        $this->load();

        foreach ($this->entries as $entry) {
            if ($entry->id === $id) {
                return $entry;
            }
        }

        return null;
    }

    public function put(PageIndexEntry $entry): void
    {
        $this->load();
        $this->entries[$entry->slug] = $entry;
        $this->dirty = true;
        $this->save();
    }

    public function delete(string $slug): void
    {
        $this->load();

        if (isset($this->entries[$slug])) {
            unset($this->entries[$slug]);
            $this->dirty = true;
            $this->save();
        }
    }

    public function query(?PageIndexQuery $query = null): array
    {
        $this->load();
        $query ??= new PageIndexQuery();

        $entries = array_values($this->entries);

        if ($query->status !== null) {
            $entries = array_values(array_filter(
                $entries,
                static fn (PageIndexEntry $entry): bool => $entry->status === $query->status,
            ));
        }

        usort($entries, static function (PageIndexEntry $left, PageIndexEntry $right) use ($query): int {
            $comparison = $left->sortValue($query->orderBy) <=> $right->sortValue($query->orderBy);

            if ($comparison === 0) {
                $comparison = $left->slug <=> $right->slug;
            }

            return $query->orderDirection === 'asc' ? $comparison : -$comparison;
        });

        if ($query->offset > 0) {
            $entries = array_slice($entries, $query->offset);
        }

        if ($query->limit !== null) {
            $entries = array_slice($entries, 0, $query->limit);
        }

        return $entries;
    }

    public function count(?PageIndexQuery $query = null): int
    {
        $this->load();
        $query ??= new PageIndexQuery();

        if ($query->status === null) {
            return count($this->entries);
        }

        $count = 0;
        foreach ($this->entries as $entry) {
            if ($entry->status === $query->status) {
                $count++;
            }
        }

        return $count;
    }

    public function sync(iterable $filesBySlug, PageIndexer $indexer): void
    {
        $this->load();
        $seen = [];

        foreach ($filesBySlug as $slug => $filepath) {
            $seen[$slug] = true;
            $mtime = filemtime($filepath) ?: 0;
            $entry = $this->entries[$slug] ?? null;

            if ($entry === null || $entry->mtime !== $mtime || $entry->path !== $filepath) {
                $this->entries[$slug] = $indexer->indexFile($slug, $filepath, $mtime);
                $this->dirty = true;
            }
        }

        foreach (array_keys($this->entries) as $slug) {
            if (!isset($seen[$slug])) {
                unset($this->entries[$slug]);
                $this->dirty = true;
            }
        }

        $this->save();
    }

    public function rebuild(iterable $filesBySlug, PageIndexer $indexer): void
    {
        $this->entries = [];
        $this->loaded = true;
        $this->dirty = true;

        foreach ($filesBySlug as $slug => $filepath) {
            $this->entries[$slug] = $indexer->indexFile($slug, $filepath);
        }

        $this->save();
    }

    private function load(): void
    {
        if ($this->loaded) {
            return;
        }

        $this->loaded = true;

        if (!file_exists($this->path)) {
            return;
        }

        $contents = file_get_contents($this->path);
        if ($contents === false) {
            return;
        }

        try {
            $decoded = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            $this->entries = [];
            $this->dirty = true;
            return;
        }

        if (!is_array($decoded['pages'] ?? null)) {
            return;
        }

        foreach ($decoded['pages'] as $slug => $entry) {
            if (is_array($entry)) {
                $this->entries[(string) $slug] = PageIndexEntry::fromArray($entry);
            }
        }
    }

    private function save(): void
    {
        if (!$this->dirty) {
            return;
        }

        $directory = dirname($this->path);
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $payload = ['pages' => []];
        foreach ($this->entries as $slug => $entry) {
            $payload['pages'][$slug] = $entry->toArray();
        }

        file_put_contents($this->path, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
        $this->dirty = false;
    }
}
