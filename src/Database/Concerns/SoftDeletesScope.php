<?php

declare(strict_types=1);

namespace Anvyr\Loom\Database\Concerns;

use Anvyr\Loom\Database\Model;
use Anvyr\Loom\Database\ModelBuilder;
use Anvyr\Loom\Database\Scope;

class SoftDeletesScope implements Scope
{
    public function apply(ModelBuilder $builder, Model $model): void
    {
        $column = method_exists($model, 'getDeletedAtColumn')
            ? $model->getDeletedAtColumn()
            : 'deleted_at';

        $builder->whereNull($column);
    }
}
