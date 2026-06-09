<?php

declare(strict_types=1);

namespace Anvyr\Loom\Database\Migrations;

use Anvyr\Loom\Database\Connection;
use Anvyr\Loom\Database\Schema\Blueprint;
use Anvyr\Loom\Database\Schema\Schema;

class MigrationRepository
{
    private string $table = 'migrations';

    public function __construct(
        private readonly Connection $connection,
        private readonly Schema $schema,
    ) {
    }

    /** @return list<string> */
    public function getRan(): array
    {
        if (!$this->repositoryExists()) {
            return [];
        }

        return $this->connection->table($this->table)
            ->orderBy('batch', 'asc')
            ->orderBy('migration', 'asc')
            ->pluck('migration')
            ->all();
    }

    public function log(string $file, int $batch): void
    {
        $this->connection->table($this->table)->insert([
            'migration' => $file,
            'batch' => $batch
        ]);
    }

    public function delete(string $file): void
    {
        $this->connection->table($this->table)
            ->where('migration', '=', $file)
            ->delete();
    }

    public function getNextBatchNumber(): int
    {
        return $this->getLastBatchNumber() + 1;
    }

    public function getLastBatchNumber(): int
    {
        if (!$this->repositoryExists()) {
            return 0;
        }

        $batch = $this->connection->table($this->table)
            ->orderBy('batch', 'desc')
            ->first();

        return (int) ($batch['batch'] ?? 0);
    }

    public function createRepository(): void
    {
        $this->schema->create($this->table, function (Blueprint $table) {
            $table->id();
            $table->string('migration');
            $table->integer('batch');
        });
    }

    public function repositoryExists(): bool
    {
        return $this->connection->tableExists($this->table);
    }
}
