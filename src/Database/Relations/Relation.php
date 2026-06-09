<?php

declare(strict_types=1);

namespace Anvyr\Loom\Database\Relations;

use Anvyr\Loom\Database\Collection;
use Anvyr\Loom\Database\Connection;
use Anvyr\Loom\Database\Model;

abstract class Relation
{
    protected Model $parent;
    protected Model $related;
    protected string $foreignKey;
    protected string $localKey;
    protected ?\Anvyr\Loom\Database\ModelBuilder $eagerBuilder = null;

    /**
     * @param class-string<Model> $related
     */
    public function __construct(
        Model $parent,
        string $related,
        string $foreignKey,
        string $localKey,
    ) {
        $this->parent = $parent;
        $this->related = new $related();
        $this->foreignKey = $foreignKey;
        $this->localKey = $localKey;
    }

    /** @return Model|Collection<Model>|null */
    abstract public function getResults(): Model|Collection|null;

    abstract public function addConstraints(): void;

    /**
     * @param Collection<Model> $models
     */
    abstract public function addEagerConstraints(Collection $models): void;

    /**
     * @param Collection<Model> $models
     * @param Collection<Model> $results
     * @return Collection<Model>
     */
    abstract public function match(Collection $models, Collection $results, string $relation): Collection;

    protected function newQueryBuilder(): \Anvyr\Loom\Database\ModelBuilder
    {
        $query = $this->related->newQueryWithoutScopes();
        $this->applyScopes($query);
        return $query;
    }

    protected function newEagerQueryBuilder(): \Anvyr\Loom\Database\ModelBuilder
    {
        if ($this->eagerBuilder === null) {
            $this->eagerBuilder = $this->newQueryBuilder();
        }

        return $this->eagerBuilder;
    }

    protected function applyScopes(\Anvyr\Loom\Database\ModelBuilder $builder): void
    {
        foreach ($this->related->getGlobalScopes() as $scope) {
            if (!$builder->getRemovedScopes() || !isset($builder->getRemovedScopes()[$scope::class])) {
                $scope->apply($builder, $this->related);
            }
        }
    }

    /**
     * @return Collection<Model>
     */
    public function getEager(): Collection
    {
        // Use the eager builder that has constraints from addEagerConstraints()
        return $this->newEagerQueryBuilder()->get();
    }

    /**
     * Build a dictionary from a collection keyed by an attribute.
     *
     * @param Collection<Model> $results
     * @return array<string|int, list<Model>>
     */
    protected function buildDictionary(Collection $results, string $key): array
    {
        $dictionary = [];

        foreach ($results as $result) {
            $value = $result->getAttribute($key);

            if ($value !== null) {
                $dictionary[$value][] = $result;
            }
        }

        return $dictionary;
    }

    public function getForeignKey(): string
    {
        return $this->foreignKey;
    }

    public function getLocalKey(): string
    {
        return $this->localKey;
    }

    public function getParent(): Model
    {
        return $this->parent;
    }

    public function getRelated(): Model
    {
        return $this->related;
    }

    public function getConnection(): Connection
    {
        return $this->related->getConnection();
    }
}
