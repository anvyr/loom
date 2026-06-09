<?php

declare(strict_types=1);

namespace Anvyr\Loom\Database\Schema\Grammars;

use Anvyr\Loom\Database\Schema\Blueprint;

/**
 * @phpstan-import-type Column from Blueprint
 */
abstract class Grammar
{
    /** @var list<string> */
    protected array $modifiers = [];

    abstract public function compileCreate(Blueprint $blueprint): string;
    abstract public function compileDrop(Blueprint $blueprint): string;
    abstract public function compileDropIfExists(Blueprint $blueprint): string;
    /** @return list<string> */
    abstract public function compileIndexes(Blueprint $blueprint): array;

    /** @return list<string> */
    abstract public function compileForeign(Blueprint $blueprint): array;
    abstract protected function wrap(string $value): string;

    /** @param Column $column */
    abstract protected function typeString(array $column): string;

    /** @param Column $column */
    abstract protected function typeText(array $column): string;

    /** @param Column $column */
    abstract protected function typeLongText(array $column): string;

    /** @param Column $column */
    abstract protected function typeInteger(array $column): string;

    /** @param Column $column */
    abstract protected function typeBigInteger(array $column): string;

    /** @param Column $column */
    abstract protected function typeBoolean(array $column): string;

    /** @param Column $column */
    abstract protected function typeTimestamp(array $column): string;

    /** @param Column $column */
    abstract protected function typeJson(array $column): string;

    /** @return list<string> */
    public function compile(Blueprint $blueprint): array
    {
        if ($blueprint->creating()) {
            return array_merge(
                [$this->compileCreate($blueprint)],
                $this->compileIndexes($blueprint),
                $this->compileForeign($blueprint)
            );
        }

        if ($blueprint->dropping()) {
            return [$this->compileDrop($blueprint)];
        }

        return [];
    }

    /** @return list<string> */
    protected function getColumns(Blueprint $blueprint): array
    {
        $columns = [];

        foreach ($blueprint->getColumns() as $column) {
            $sql = $this->wrap($column['name']) . ' ' . $this->getType($column);
            $columns[] = $this->addModifiers($sql, $blueprint, $column);
        }

        return $columns;
    }

    /** @param Column $column */
    protected function getType(array $column): string
    {
        $method = 'type' . ucfirst($column['type']);
        return $this->{$method}($column);
    }

    /** @param Column $column */
    protected function addModifiers(string $sql, Blueprint $blueprint, array $column): string
    {
        foreach ($this->modifiers as $modifier) {
            if (method_exists($this, $method = "modify{$modifier}")) {
                $sql .= $this->{$method}($blueprint, $column);
            }
        }

        return $sql;
    }

    /** @param Column $column */
    protected function modifyNullable(Blueprint $blueprint, array $column): string
    {
        if ($column['autoIncrement'] ?? false) {
            return '';
        }

        return $column['nullable'] ? '' : ' NOT NULL';
    }

    /** @param Column $column */
    protected function modifyDefault(Blueprint $blueprint, array $column): string
    {
        if (!empty($column['useCurrent'])) {
            return ' DEFAULT CURRENT_TIMESTAMP';
        }

        if (!isset($column['default'])) {
            return '';
        }

        return ' DEFAULT ' . $this->quoteString($column['default']);
    }

    protected function quoteString(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }
        return "'" . str_replace("'", "''", (string) $value) . "'";
    }
}
