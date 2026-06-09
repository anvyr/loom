<?php

declare(strict_types=1);

namespace Anvyr\Loom\Database\Concerns;

use Anvyr\Loom\Database\ModelBuilder;

trait SoftDeletes
{
    public function getDeletedAtColumn(): string
    {
        return 'deleted_at';
    }

    public function trashed(): bool
    {
        return $this->getAttribute($this->getDeletedAtColumn()) !== null;
    }

    public function softDelete(): bool
    {
        $this->setAttribute($this->getDeletedAtColumn(), date('Y-m-d H:i:s'));
        return $this->save();
    }

    public function restore(): bool
    {
        $this->setAttribute($this->getDeletedAtColumn(), null);
        return $this->save();
    }

    public function scopeWithTrashed(ModelBuilder $builder): void
    {
        $builder->removeGlobalScope(SoftDeletesScope::class);
    }

    public function scopeOnlyTrashed(ModelBuilder $builder): void
    {
        $builder->removeGlobalScope(SoftDeletesScope::class);
        $builder->whereNotNull($this->getDeletedAtColumn());
    }

    public static function bootSoftDeletes(): void
    {
        static::addGlobalScope(new SoftDeletesScope());
    }
}
