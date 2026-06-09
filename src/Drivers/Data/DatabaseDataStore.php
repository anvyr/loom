<?php

declare(strict_types=1);

namespace Anvyr\Loom\Drivers\Data;

use Anvyr\Loom\Contracts\DataStore;
use Anvyr\Loom\Database\Connection;

class DatabaseDataStore implements DataStore
{
    private const TABLE = 'data_store';

    public function __construct(
        private readonly Connection $db
    ) {
    }

    public function get(string $collection, string $key): ?array
    {
        $row = $this->db->table(self::TABLE)
            ->where('collection', '=', $collection)
            ->where('key', '=', $key)
            ->first();

        if ($row === null) {
            return null;
        }

        $data = json_decode($row['data'] ?? '{}', true);

        return is_array($data) ? $data : null;
    }

    public function put(string $collection, string $key, array $data): void
    {
        $data['_key'] = $key;
        $data['_updated_at'] = date('Y-m-d H:i:s');
        $data['_created_at'] ??= $data['_updated_at'];

        $json = json_encode($data, JSON_UNESCAPED_UNICODE);
        $now = date('Y-m-d H:i:s');

        $this->db->table(self::TABLE)->upsert(
            [
                'collection' => $collection,
                'key' => $key,
                'data' => $json,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            ['collection', 'key'],
            ['data', 'updated_at']
        );
    }

    public function forget(string $collection, string $key): bool
    {
        $deleted = $this->db->table(self::TABLE)
            ->where('collection', '=', $collection)
            ->where('key', '=', $key)
            ->delete();

        return $deleted > 0;
    }

    public function has(string $collection, string $key): bool
    {
        return $this->db->table(self::TABLE)
            ->where('collection', '=', $collection)
            ->where('key', '=', $key)
            ->exists();
    }

    public function all(string $collection): array
    {
        $rows = $this->db->table(self::TABLE)
            ->where('collection', '=', $collection)
            ->get();

        $records = [];

        foreach ($rows as $row) {
            $data = json_decode($row['data'] ?? '{}', true);
            if (is_array($data)) {
                $records[$row['key']] = $data;
            }
        }

        return $records;
    }

    public function filter(string $collection, callable $predicate): array
    {
        $all = $this->all($collection);

        return array_filter($all, $predicate);
    }

    public function clear(string $collection): void
    {
        $this->db->table(self::TABLE)
            ->where('collection', '=', $collection)
            ->delete();
    }

    public function driver(): string
    {
        return 'database';
    }
}
