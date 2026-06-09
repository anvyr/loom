<?php

declare(strict_types=1);

namespace Anvyr\Loom\Database;

interface Scope
{
    public function apply(ModelBuilder $builder, Model $model): void;
}
