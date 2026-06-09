<?php

declare(strict_types=1);

namespace Anvyr\Loom\Database\Relations;

use Anvyr\Loom\Database\Collection;
use Anvyr\Loom\Database\Model;

class HasMany extends Relation
{
    /** @return Collection<Model> */
    public function getResults(): Collection
    {
        return $this->newQueryBuilder()
            ->where($this->foreignKey, '=', $this->parent->getAttribute($this->localKey))
            ->get();
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
            $model->setRelation($relation, $this->related->newCollection($dictionary[$key] ?? []));
        }

        return $models;
    }
}
