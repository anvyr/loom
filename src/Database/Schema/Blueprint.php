<?php

declare(strict_types=1);

namespace Anvyr\Loom\Database\Schema;

/**
 * @phpstan-type Column array{type: string, name: string, nullable: bool, length?: int, autoIncrement?: bool, unsigned?: bool, default?: mixed, useCurrent?: bool}
 * @phpstan-type Command array{type: 'index'|'unique'|'primary', columns: list<string>, name: ?string}
 */
class Blueprint
{
    private string $table;

    /** @var list<Column> */
    private array $columns = [];

    /** @var list<Command|ForeignKeyDefinition> */
    private array $commands = [];
    private bool $creating = false;
    private bool $dropping = false;

    public function __construct(string $table)
    {
        $this->table = $table;
    }

    public function create(): void
    {
        $this->creating = true;
    }

    public function drop(): void
    {
        $this->dropping = true;
    }

    public function getTable(): string
    {
        return $this->table;
    }

    /** @return list<Column> */
    public function getColumns(): array
    {
        return $this->columns;
    }

    /** @return list<Command|ForeignKeyDefinition> */
    public function getCommands(): array
    {
        return $this->commands;
    }

    public function creating(): bool
    {
        return $this->creating;
    }

    public function dropping(): bool
    {
        return $this->dropping;
    }

    public function id(string $column = 'id'): self
    {
        return $this->bigInteger($column, true, true);
    }

    public function string(string $column, int $length = 255): self
    {
        return $this->addColumn('string', $column, compact('length'));
    }

    public function text(string $column): self
    {
        return $this->addColumn('text', $column);
    }

    public function longText(string $column): self
    {
        return $this->addColumn('longText', $column);
    }

    public function integer(string $column, bool $autoIncrement = false, bool $unsigned = false): self
    {
        return $this->addColumn('integer', $column, compact('autoIncrement', 'unsigned'));
    }

    public function bigInteger(string $column, bool $autoIncrement = false, bool $unsigned = false): self
    {
        return $this->addColumn('bigInteger', $column, compact('autoIncrement', 'unsigned'));
    }

    public function json(string $column): self
    {
        return $this->addColumn('json', $column);
    }

    public function boolean(string $column): self
    {
        return $this->addColumn('boolean', $column);
    }

    public function timestamps(): void
    {
        $this->timestamp('created_at')->nullable();
        $this->timestamp('updated_at')->nullable();
    }

    public function timestamp(string $column): self
    {
        return $this->addColumn('timestamp', $column);
    }

    public function nullable(bool $value = true): self
    {
        $key = array_key_last($this->columns);
        if ($key === null) {
            throw new \LogicException('No column has been defined.');
        }

        $this->columns[$key]['nullable'] = $value;
        return $this;
    }

    public function default(mixed $value): self
    {
        $key = array_key_last($this->columns);
        if ($key === null) {
            throw new \LogicException('No column has been defined.');
        }

        $this->columns[$key]['default'] = $value;
        return $this;
    }

    public function useCurrent(): self
    {
        $key = array_key_last($this->columns);
        if ($key === null) {
            throw new \LogicException('No column has been defined.');
        }

        $this->columns[$key]['useCurrent'] = true;
        return $this;
    }

    public function unsigned(): self
    {
        $key = array_key_last($this->columns);
        if ($key === null) {
            throw new \LogicException('No column has been defined.');
        }

        $this->columns[$key]['unsigned'] = true;
        return $this;
    }

    /** @param string|list<string>|null $columns */
    public function index(string|array|null $columns = null, ?string $name = null): self
    {
        if ($columns === null) {
            $lastColumn = $this->lastColumnName();
            $columns = [$lastColumn];
        } else {
            $columns = (array) $columns;
        }
        return $this->addCommand('index', compact('columns', 'name'));
    }

    /** @param string|list<string>|null $columns */
    public function unique(string|array|null $columns = null, ?string $name = null): self
    {
        if ($columns === null) {
            $lastColumn = $this->lastColumnName();
            $columns = [$lastColumn];
        } else {
            $columns = (array) $columns;
        }
        return $this->addCommand('unique', compact('columns', 'name'));
    }

    /** @param string|list<string>|null $columns */
    public function primary(string|array|null $columns = null, ?string $name = null): self
    {
        if ($columns === null) {
            $lastColumn = $this->lastColumnName();
            $columns = [$lastColumn];
        } else {
            $columns = (array) $columns;
        }
        return $this->addCommand('primary', compact('columns', 'name'));
    }

    /** @param string|list<string> $columns */
    public function foreign(string|array $columns, ?string $name = null): ForeignKeyDefinition
    {
        $command = new ForeignKeyDefinition((array) $columns, $name);
        $this->commands[] = $command;
        return $command;
    }

    /**
     * @param 'index'|'unique'|'primary' $type
     * @param array{columns?: list<string>, name?: ?string} $parameters
     */
    private function addCommand(string $type, array $parameters = []): self
    {
        $this->commands[] = [
            'type' => $type,
            'columns' => $parameters['columns'] ?? [],
            'name' => $parameters['name'] ?? null,
        ];
        return $this;
    }

    /** @param array<string, mixed> $parameters */
    private function addColumn(string $type, string $name, array $parameters = []): self
    {
        /** @var Column $column */
        $column = array_merge(compact('type', 'name'), $parameters, [
            'nullable' => false
        ]);
        $this->columns[] = $column;
        return $this;
    }

    private function lastColumnName(): string
    {
        $key = array_key_last($this->columns);
        if ($key === null) {
            throw new \LogicException('No column has been defined.');
        }

        return $this->columns[$key]['name'];
    }
}
