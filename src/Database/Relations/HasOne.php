<?php

declare(strict_types=1);

namespace Anvyr\Loom\Database\Relations;

use Anvyr\Loom\Database\Collection;
use Anvyr\Loom\Database\Model;

class HasOne extends Relation
{
    public function getResults(): Model|null
    {
        return $this->newQueryBuilder()
            ->where($this->foreignKey, '=', $this->parent->getAttribute($this->localKey))
            ->first();
    }

    public function addConstraints(): void
    {
        $this->newQueryBuilder()
            ->where($this->foreignKey, '=', $this->parent->getAttribute($this->localKey));
    }

    /** @param Collection<Model> $models */
    public function addEagerConstraints(Collection $models): void
    {
        $keys = [];
        foreach ($models as $model) {
            $keys[] = $model->getAttribute($this->localKey);
        }

        $this->newEagerQueryBuilder()
            ->whereIn($this->foreignKey, array_values(array_unique(array_filter($keys))));
    }

    /**
     * @param Collection<Model> $models
     * @param Collection<Model> $results
     * @return Collection<Model>
     */
    public function match(Collection $models, Collection $results, string $relation): Collection
    {
        $dictionary = $this->buildDictionary($results, $this->foreignKey);

        foreach ($models as $model) {
            $key = $model->getAttribute($this->localKey);
            $model->setRelation($relation, $dictionary[$key][0] ?? null);
        }

        return $models;
    }
}
