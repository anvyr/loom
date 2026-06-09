<?php

declare(strict_types=1);

namespace Anvyr\Loom\Content\Index;

use Anvyr\Loom\Database\Connection;

final class SqlitePageIndex implements PageIndex
{
    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    public function get(string $slug): ?PageIndexEntry
    {
        $rows = $this->connection->query('SELECT * FROM page_index WHERE slug = ? LIMIT 1', [$slug]);

        return isset($rows[0]) ? $this->mapRowToEntry($rows[0]) : null;
    }

    public function getById(string $id): ?PageIndexEntry
    {
        $rows = $this->connection->query('SELECT * FROM page_index WHERE id = ? LIMIT 1', [$id]);

        return isset($rows[0]) ? $this->mapRowToEntry($rows[0]) : null;
    }

    public function put(PageIndexEntry $entry): void
    {
        $pdo = $this->connection->getPdo();
        $statement = $pdo->prepare(
            'INSERT INTO page_index (
                id, slug, path, mtime, format, title, status, layout, excerpt, trusted,
                created_at, updated_at, published_at, meta_json
            ) VALUES (
                :id, :slug, :path, :mtime, :format, :title, :status, :layout, :excerpt, :trusted,
                :created_at, :updated_at, :published_at, :meta_json
            )
            ON CONFLICT(slug) DO UPDATE SET
                id = excluded.id,
                path = excluded.path,
                mtime = excluded.mtime,
                format = excluded.format,
                title = excluded.title,
                status = excluded.status,
                layout = excluded.layout,
                excerpt = excluded.excerpt,
                trusted = excluded.trusted,
                created_at = excluded.created_at,
                updated_at = excluded.updated_at,
                published_at = excluded.published_at,
                meta_json = excluded.meta_json'
        );

        $statement->execute($this->entryBindings($entry));
    }

    public function delete(string $slug): void
    {
        $this->connection->statement('DELETE FROM page_index WHERE slug = ?', [$slug]);
    }

    public function query(?PageIndexQuery $query = null): array
    {
        $query ??= new PageIndexQuery();

        [$sql, $bindings] = $this->buildSelectSql($query, 'SELECT * FROM page_index');
        $rows = $this->connection->query($sql, $bindings);

        return array_map(fn (array $row): PageIndexEntry => $this->mapRowToEntry($row), $rows);
    }

    public function count(?PageIndexQuery $query = null): int
    {
        $query ??= new PageIndexQuery();
        $bindings = [];
        $sql = 'SELECT COUNT(*) as cnt FROM page_index';

        if ($query->status !== null) {
            $sql .= ' WHERE status = ?';
            $bindings[] = $query->status;
        }

        $rows = $this->connection->query($sql, $bindings);

        return (int) ($rows[0]['cnt'] ?? 0);
    }

    public function sync(iterable $filesBySlug, PageIndexer $indexer): void
    {
        $existing = $this->loadExistingEntries();
        $seen = [];

        $this->connection->beginTransaction();

        try {
            foreach ($filesBySlug as $slug => $filepath) {
                $seen[$slug] = true;
                $mtime = filemtime($filepath) ?: 0;
                $entry = $existing[$slug] ?? null;

                if ($entry === null || $entry['mtime'] !== $mtime || $entry['path'] !== $filepath) {
                    $this->put($indexer->indexFile($slug, $filepath, $mtime));
                }
            }

            foreach (array_keys($existing) as $slug) {
                if (!isset($seen[$slug])) {
                    $this->delete($slug);
                }
            }

            $this->connection->commit();
        } catch (\Throwable $e) {
            $this->connection->rollback();
            throw $e;
        }
    }

    public function rebuild(iterable $filesBySlug, PageIndexer $indexer): void
    {
        $this->connection->beginTransaction();

        try {
            $this->connection->statement('DELETE FROM page_index');

            foreach ($filesBySlug as $slug => $filepath) {
                $this->put($indexer->indexFile($slug, $filepath));
            }

            $this->connection->commit();
        } catch (\Throwable $e) {
            $this->connection->rollback();
            throw $e;
        }
    }

    /**
     * @return array<string, array{path: string, mtime: int}>
     */
    private function loadExistingEntries(): array
    {
        $rows = $this->connection->query('SELECT slug, path, mtime FROM page_index');
        $existing = [];

        foreach ($rows as $row) {
            $existing[(string) $row['slug']] = [
                'path' => (string) $row['path'],
                'mtime' => (int) $row['mtime'],
            ];
        }

        return $existing;
    }

    /**
     * @return array{0: string, 1: list<mixed>}
     */
    private function buildSelectSql(PageIndexQuery $query, string $baseSql): array
    {
        $bindings = [];
        $sql = $baseSql;

        if ($query->status !== null) {
            $sql .= ' WHERE status = ?';
            $bindings[] = $query->status;
        }

        $orderBy = match ($query->orderBy) {
            'slug' => 'slug',
            'title' => 'title',
            'status' => 'status',
            'updated_at' => 'updated_at',
            'published_at' => 'published_at',
            default => 'created_at',
        };
        $direction = $query->orderDirection === 'asc' ? 'ASC' : 'DESC';
        $sql .= " ORDER BY {$orderBy} {$direction}, slug ASC";

        if ($query->limit !== null) {
            $sql .= ' LIMIT ?';
            $bindings[] = $query->limit;
        }

        if ($query->offset > 0) {
            if ($query->limit === null) {
                $sql .= ' LIMIT -1';
            }

            $sql .= ' OFFSET ?';
            $bindings[] = $query->offset;
        }

        return [$sql, $bindings];
    }

    /** @param array<string, mixed> $row */
    private function mapRowToEntry(array $row): PageIndexEntry
    {
        $meta = json_decode((string) ($row['meta_json'] ?? '{}'), true);

        return new PageIndexEntry(
            id: (string) ($row['id'] ?? uuid_v7()),
            slug: (string) $row['slug'],
            path: (string) $row['path'],
            mtime: (int) $row['mtime'],
            format: (string) $row['format'],
            title: (string) $row['title'],
            status: (string) $row['status'],
            layout: isset($row['layout']) ? (string) $row['layout'] : null,
            excerpt: isset($row['excerpt']) ? (string) $row['excerpt'] : null,
            trusted: (bool) ($row['trusted'] ?? false),
            createdAt: isset($row['created_at']) ? (string) $row['created_at'] : null,
            updatedAt: isset($row['updated_at']) ? (string) $row['updated_at'] : null,
            publishedAt: isset($row['published_at']) ? (string) $row['published_at'] : null,
            meta: is_array($meta) ? $meta : [],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function entryBindings(PageIndexEntry $entry): array
    {
        return [
            'id' => $entry->id,
            'slug' => $entry->slug,
            'path' => $entry->path,
            'mtime' => $entry->mtime,
            'format' => $entry->format,
            'title' => $entry->title,
            'status' => $entry->status,
            'layout' => $entry->layout,
            'excerpt' => $entry->excerpt,
            'trusted' => $entry->trusted ? 1 : 0,
            'created_at' => $entry->createdAt,
            'updated_at' => $entry->updatedAt,
            'published_at' => $entry->publishedAt,
            'meta_json' => json_encode($entry->meta, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
        ];
    }
}
