<?php

declare(strict_types=1);

namespace Anvyr\Loom\Database\Relations;

use Anvyr\Loom\Database\Collection;
use Anvyr\Loom\Database\Model;

class BelongsTo extends Relation
{
    /**
     * @param class-string<Model> $related
     */
    public function __construct(
        Model $parent,
        string $related,
        string $foreignKey,
        string $ownerKey,
    ) {
        parent::__construct($parent, $related, $foreignKey, $ownerKey);
    }

    public function getResults(): Model|null
    {
        return $this->newQueryBuilder()
            ->where($this->ownerKey(), '=', $this->parent->getAttribute($this->foreignKey))
            ->first();
    }

    public function addConstraints(): void
    {
        $this->newQueryBuilder()
            ->where($this->ownerKey(), '=', $this->parent->getAttribute($this->foreignKey));
    }

    /** @param Collection<Model> $models */
    public function addEagerConstraints(Collection $models): void
    {
        $keys = [];
        foreach ($models as $model) {
            $keys[] = $model->getAttribute($this->foreignKey);
        }

        $this->newEagerQueryBuilder()
            ->whereIn($this->ownerKey(), array_values(array_unique(array_filter($keys))));
    }

    /**
     * @param Collection<Model> $models
     * @param Collection<Model> $results
     * @return Collection<Model>
     */
    public function match(Collection $models, Collection $results, string $relation): Collection
    {
        $dictionary = [];
        foreach ($results as $result) {
            $dictionary[$result->getAttribute($this->ownerKey())] = $result;
        }

        foreach ($models as $model) {
            $key = $model->getAttribute($this->foreignKey);
            $model->setRelation($relation, $dictionary[$key] ?? null);
        }

        return $models;
    }

    private function ownerKey(): string
    {
        return $this->localKey;
    }

    /**
     * @param Collection<Model> $models
     * @return Collection<Model>
     */
    public function initRelation(Collection $models, string $relation): Collection
    {
        foreach ($models as $model) {
            $model->setRelation($relation, null);
        }

        return $models;
    }
}
