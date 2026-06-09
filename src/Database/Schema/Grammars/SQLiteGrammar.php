<?php

declare(strict_types=1);

namespace Anvyr\Loom\Database\Schema\Grammars;

use Anvyr\Loom\Database\Schema\Blueprint;
use Anvyr\Loom\Database\Schema\ForeignKeyDefinition;

/** @phpstan-import-type Column from Blueprint */
class SQLiteGrammar extends Grammar
{
    /** @var list<string> */
    protected array $modifiers = ['Nullable', 'Default', 'Incrementing'];

    public function compileCreate(Blueprint $blueprint): string
    {
        $columns = $this->getColumns($blueprint);

        foreach ($blueprint->getCommands() as $command) {
            if (!$command instanceof ForeignKeyDefinition) {
                continue;
            }

            $cols = $this->columnize($command->columns);
            $onTable = $this->wrap($command->onTable);
            $refs = $this->columnize($command->references);

            $sql = "FOREIGN KEY ({$cols}) REFERENCES {$onTable} ({$refs})";

            if ($command->onDelete) {
                $sql .= " ON DELETE {$command->onDelete}";
            }
            if ($command->onUpdate) {
                $sql .= " ON UPDATE {$command->onUpdate}";
            }

            $columns[] = $sql;
        }

        foreach ($blueprint->getCommands() as $command) {
            if ($command instanceof ForeignKeyDefinition) {
                continue;
            }

            $cols = $this->columnize($command['columns']);

            if ($command['type'] === 'primary') {
                $columns[] = "PRIMARY KEY ({$cols})";
            } elseif ($command['type'] === 'unique') {
                $columns[] = "UNIQUE ({$cols})";
            }
        }

        return 'CREATE TABLE ' . $this->wrap($blueprint->getTable()) . ' (' . implode(', ', $columns) . ')';
    }

    /** @return list<string> */
    public function compileIndexes(Blueprint $blueprint): array
    {
        $statements = [];

        foreach ($blueprint->getCommands() as $command) {
            if ($command instanceof ForeignKeyDefinition) {
                continue;
            }

            if ($command['type'] === 'index') {
                $columns = $this->columnize($command['columns']);
                $table = $this->wrap($blueprint->getTable());
                $indexName = $this->wrap($command['name'] ?? $this->generateIndexName($blueprint->getTable(), $command['columns'], 'index'));

                $statements[] = "CREATE INDEX {$indexName} ON {$table} ({$columns})";
            }
        }

        return $statements;
    }

    /** @return list<string> */
    public function compileForeign(Blueprint $blueprint): array
    {
        return [];
    }

    public function compileDrop(Blueprint $blueprint): string
    {
        return 'DROP TABLE ' . $this->wrap($blueprint->getTable());
    }

    public function compileDropIfExists(Blueprint $blueprint): string
    {
        return 'DROP TABLE IF EXISTS ' . $this->wrap($blueprint->getTable());
    }

    /** @param Column $column */
    protected function typeString(array $column): string
    {
        return 'VARCHAR';
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
        return 'INTEGER';
    }

    /** @param Column $column */
    protected function typeBigInteger(array $column): string
    {
        return 'INTEGER';
    }

    /** @param Column $column */
    protected function typeBoolean(array $column): string
    {
        return 'INTEGER';
    }

    /** @param Column $column */
    protected function typeTimestamp(array $column): string
    {
        return 'DATETIME';
    }

    /** @param Column $column */
    protected function typeJson(array $column): string
    {
        return 'TEXT';
    }

    /** @param Column $column */
    protected function modifyIncrementing(Blueprint $blueprint, array $column): string
    {
        if (in_array($column['type'], ['integer', 'bigInteger'], true) && ($column['autoIncrement'] ?? false)) {
            return ' PRIMARY KEY AUTOINCREMENT';
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
