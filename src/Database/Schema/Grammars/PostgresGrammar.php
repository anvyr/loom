<?php

declare(strict_types=1);

namespace Anvyr\Loom\Database\Schema\Grammars;

use Anvyr\Loom\Database\Schema\Blueprint;
use Anvyr\Loom\Database\Schema\ForeignKeyDefinition;

/** @phpstan-import-type Column from Blueprint */
class PostgresGrammar extends Grammar
{
    /** @var list<string> */
    protected array $modifiers = ['Nullable', 'Default'];

    public function compileCreate(Blueprint $blueprint): string
    {
        $columns = implode(', ', $this->getColumns($blueprint));
        return 'CREATE TABLE ' . $this->wrap($blueprint->getTable()) . " ($columns)";
    }

    public function compileDrop(Blueprint $blueprint): string
    {
        return 'DROP TABLE ' . $this->wrap($blueprint->getTable());
    }

    public function compileDropIfExists(Blueprint $blueprint): string
    {
        return 'DROP TABLE IF EXISTS ' . $this->wrap($blueprint->getTable());
    }

    /** @return list<string> */
    public function compileIndexes(Blueprint $blueprint): array
    {
        $statements = [];

        foreach ($blueprint->getCommands() as $command) {
            if ($command instanceof ForeignKeyDefinition) {
                continue;
            }

            if ($command['type'] === 'primary') {
                $columns = $this->columnize($command['columns']);
                $statements[] = 'ALTER TABLE ' . $this->wrap($blueprint->getTable()) . " ADD PRIMARY KEY ({$columns})";
                continue;
            }

            $columns = $this->columnize($command['columns']);
            $table = $this->wrap($blueprint->getTable());
            $indexName = $this->wrap($command['name'] ?? $this->generateIndexName($blueprint->getTable(), $command['columns'], $command['type']));

            if ($command['type'] === 'unique') {
                $statements[] = "CREATE UNIQUE INDEX {$indexName} ON {$table} ({$columns})";
            } elseif ($command['type'] === 'index') {
                $statements[] = "CREATE INDEX {$indexName} ON {$table} ({$columns})";
            }
        }

        return $statements;
    }

    /** @return list<string> */
    public function compileForeign(Blueprint $blueprint): array
    {
        $statements = [];

        foreach ($blueprint->getCommands() as $command) {
            if (!$command instanceof ForeignKeyDefinition) {
                continue;
            }

            $table = $this->wrap($blueprint->getTable());
            $columns = $this->columnize($command->columns);
            $onTable = $this->wrap($command->onTable);
            $references = $this->columnize($command->references);
            $name = $this->wrap($command->name ?? $this->generateIndexName($blueprint->getTable(), $command->columns, 'foreign'));

            $sql = "ALTER TABLE {$table} ADD CONSTRAINT {$name} FOREIGN KEY ({$columns}) REFERENCES {$onTable} ({$references})";

            if ($command->onDelete) {
                $sql .= " ON DELETE {$command->onDelete}";
            }

            if ($command->onUpdate) {
                $sql .= " ON UPDATE {$command->onUpdate}";
            }

            $statements[] = $sql;
        }

        return $statements;
    }

    /** @param Column $column */
    protected function typeString(array $column): string
    {
        return 'VARCHAR(' . ($column['length'] ?? 255) . ')';
    }

    /** @param Column $column */
    protected function typeText(array $column): string
    {
        return 'TEXT';
    }

    /** @param Column $column */
    protected function typeLongText(array $column): string
    {
        return 'TEXT';
    }

    /** @param Column $column */
    protected function typeInteger(array $column): string
    {
        if ($column['autoIncrement'] ?? false) {
            return 'SERIAL';
        }
        return 'INTEGER';
    }

    /** @param Column $column */
    protected function typeBigInteger(array $column): string
    {
        if ($column['autoIncrement'] ?? false) {
            return 'BIGSERIAL';
        }
        return 'BIGINT';
    }

    /** @param Column $column */
    protected function typeBoolean(array $column): string
    {
        return 'BOOLEAN';
    }

    /** @param Column $column */
    protected function typeTimestamp(array $column): string
    {
        return 'TIMESTAMP(0) WITHOUT TIME ZONE';
    }

    /** @param Column $column */
    protected function typeJson(array $column): string
    {
        return 'JSONB';
    }

    /** @param Column $column */
    protected function modifyIncrementing(Blueprint $blueprint, array $column): string
    {
        if (in_array($column['type'], ['integer', 'bigInteger']) && ($column['autoIncrement'] ?? false)) {
            return ' PRIMARY KEY';
        }
        return '';
    }

    protected function wrap(string $value): string
    {
        return '"' . str_replace('"', '""', $value) . '"';
    }

    /** @param list<string> $columns */
    protected function columnize(array $columns): string
    {
        return implode(', ', array_map([$this, 'wrap'], $columns));
    }

    /** @param list<string> $columns */
    protected function generateIndexName(string $table, array $columns, string $type): string
    {
        return strtolower($table . '_' . implode('_', $columns) . '_' . $type);
    }
}
