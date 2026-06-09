<?php

declare(strict_types=1);

namespace Anvyr\Loom\Database\Schema;

class ForeignKeyDefinition
{
    /** @var list<string> */
    public array $columns;
    public ?string $name;
    public string $onTable;

    /** @var list<string> */
    public array $references;
    public string $onDelete = 'RESTRICT';
    public string $onUpdate = 'RESTRICT';

    /** @param list<string> $columns */
    public function __construct(array $columns, ?string $name = null)
    {
        $this->columns = $columns;
        $this->name = $name;
    }

    /** @param string|list<string> $columns */
    public function references(string|array $columns): self
    {
        $this->references = (array) $columns;
        return $this;
    }

    public function on(string $table): self
    {
        $this->onTable = $table;
        return $this;
    }

    public function onDelete(string $action): self
    {
        $this->onDelete = strtoupper($action);
        return $this;
    }

    public function onUpdate(string $action): self
    {
        $this->onUpdate = strtoupper($action);
        return $this;
    }
}
