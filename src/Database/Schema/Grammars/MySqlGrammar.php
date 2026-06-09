<?php

declare(strict_types=1);

namespace Anvyr\Loom\Database\Schema\Grammars;

use Anvyr\Loom\Database\Schema\Blueprint;
use Anvyr\Loom\Database\Schema\ForeignKeyDefinition;

/** @phpstan-import-type Column from Blueprint */
class MySqlGrammar extends Grammar
{
    /** @var list<string> */
    protected array $modifiers = ['Unsigned', 'Nullable', 'Default', 'Incrementing'];

    public function compileCreate(Blueprint $blueprint): string
    {
        $columns = $this->getColumns($blueprint);

        foreach ($blueprint->getCommands() as $command) {
            if ($command instanceof ForeignKeyDefinition) {
                continue;
            }

            $cols = $this->columnize($command['columns']);

            if ($command['type'] === 'primary') {
                $columns[] = "PRIMARY KEY ({$cols})";
            } elseif ($command['type'] === 'unique') {
                $columns[] = "UNIQUE ({$cols})";
            } elseif ($command['type'] === 'index') {
                $columns[] = "INDEX ({$cols})";
            }
        }

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

        $sql = 'CREATE TABLE ' . $this->wrap($blueprint->getTable()) . ' (' . implode(', ', $columns) . ')';

        $sql .= ' ENGINE = InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

        return $sql;
    }

    /** @return list<string> */
    public function compileIndexes(Blueprint $blueprint): array
    {
        return [];
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
        return 'LONGTEXT';
    }

    /** @param Column $column */
    protected function typeInteger(array $column): string
    {
        return 'INT';
    }

    /** @param Column $column */
    protected function typeBigInteger(array $column): string
    {
        return 'BIGINT';
    }

    /** @param Column $column */
    protected function typeBoolean(array $column): string
    {
        return 'TINYINT(1)';
    }

    /** @param Column $column */
    protected function typeTimestamp(array $column): string
    {
        return 'TIMESTAMP';
    }

    /** @param Column $column */
    protected function typeJson(array $column): string
    {
        return 'JSON';
    }

    /** @param Column $column */
    protected function modifyUnsigned(Blueprint $blueprint, array $column): string
    {
        if ($column['unsigned'] ?? false) {
            return ' UNSIGNED';
        }
        return '';
    }

    /** @param Column $column */
    protected function modifyIncrementing(Blueprint $blueprint, array $column): string
    {
        if (in_array($column['type'], ['integer', 'bigInteger']) && ($column['autoIncrement'] ?? false)) {
            return ' AUTO_INCREMENT PRIMARY KEY';
        }
        return '';
    }

    protected function wrap(string $value): string
    {
        return '`' . str_replace('`', '``', $value) . '`';
    }

    /** @param list<string> $columns */
    protected function columnize(array $columns): string
    {
        return implode(', ', array_map([$this, 'wrap'], $columns));
    }
}
