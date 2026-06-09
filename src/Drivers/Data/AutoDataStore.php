<?php

declare(strict_types=1);

namespace Anvyr\Loom\Drivers\Data;

use Anvyr\Loom\Contracts\DataStore;
use Anvyr\Loom\Database\Connection;

class AutoDataStore implements DataStore
{
    private FileDataStore $fileStore;
    private ?DatabaseDataStore $dbStore = null;

    public function __construct(
        ?Connection $connection = null,
        ?string $fileBasePath = null
    ) {
        $this->fileStore = new FileDataStore($fileBasePath);

        if ($connection !== null) {
            try {
                $connection->getPdo();
                $this->dbStore = new DatabaseDataStore($connection);
            } catch (\Throwable) {
                $this->dbStore = null;
            }
        }
    }

    public function get(string $collection, string $key): ?array
    {
        $dbStore = $this->databaseStore();
        if ($dbStore !== null) {
            $data = $dbStore->get($collection, $key);
            if ($data !== null) {
                return $data;
            }
        }

        return $this->fileStore->get($collection, $key);
    }

    public function put(string $collection, string $key, array $data): void
    {
        $this->fileStore->put($collection, $key, $data);

        $dbStore = $this->databaseStore();
        if ($dbStore !== null) {
            $dbStore->put($collection, $key, $data);
        }
    }

    public function forget(string $collection, string $key): bool
    {
        $fileResult = $this->fileStore->forget($collection, $key);

        $dbStore = $this->databaseStore();
        if ($dbStore !== null) {
            $dbResult = $dbStore->forget($collection, $key);
            return $fileResult || $dbResult;
        }

        return $fileResult;
    }

    public function has(string $collection, string $key): bool
    {
        $dbStore = $this->databaseStore();
        if ($dbStore !== null && $dbStore->has($collection, $key)) {
            return true;
        }

        return $this->fileStore->has($collection, $key);
    }

    public function all(string $collection): array
    {
        $records = $this->fileStore->all($collection);

        $dbStore = $this->databaseStore();
        if ($dbStore !== null) {
            $dbRecords = $dbStore->all($collection);
            $records = array_merge($records, $dbRecords);
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
        $this->fileStore->clear($collection);

        $dbStore = $this->databaseStore();
        if ($dbStore !== null) {
            $dbStore->clear($collection);
        }
    }

    public function driver(): string
    {
        return 'auto';
    }

    public function isDatabaseAvailable(): bool
    {
        return $this->databaseStore() !== null;
    }

    public function activeDriver(): string
    {
        return $this->databaseStore() !== null ? 'database' : 'file';
    }

    public function fileStore(): FileDataStore
    {
        return $this->fileStore;
    }

    public function databaseStore(): ?DatabaseDataStore
    {
        return $this->dbStore;
    }
}
