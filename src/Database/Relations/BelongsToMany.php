<?php

declare(strict_types=1);

namespace Anvyr\Loom\Database\Relations;

use Anvyr\Loom\Database\Collection;
use Anvyr\Loom\Database\Model;

class BelongsToMany extends Relation
{
    protected string $pivotTable;
    protected string $foreignPivotKey;
    protected string $relatedPivotKey;
    protected string $parentKey;
    protected string $relatedKey;

    /** @var list<string> */
    protected array $pivotColumns = [];

    /**
     * @param class-string<Model> $related
     */
    public function __construct(
        Model $parent,
        string $related,
        string $pivotTable,
        string $foreignPivotKey,
        string $relatedPivotKey,
        string $parentKey,
        string $relatedKey,
    ) {
        parent::__construct($parent, $related, $foreignPivotKey, $parentKey);
        $this->pivotTable = $pivotTable;
        $this->foreignPivotKey = $foreignPivotKey;
        $this->relatedPivotKey = $relatedPivotKey;
        $this->parentKey = $parentKey;
        $this->relatedKey = $relatedKey;
    }

    /** @return Collection<Model> */
    public function getResults(): Collection
    {
        $builder = $this->newQueryBuilder();

        $builder->join(
            $this->pivotTable,
            $this->getRelated()->getTable() . '.' . $this->relatedKey,
            '=',
            $this->pivotTable . '.' . $this->relatedPivotKey,
        );

        $builder->where(
            $this->pivotTable . '.' . $this->foreignPivotKey,
            '=',
            $this->parent->getAttribute($this->parentKey),
        );

        $builder->select($this->getRelated()->getTable() . '.*');
        $builder->selectRaw($this->pivotTable . '.' . $this->foreignPivotKey . ' as pivot_' . $this->foreignPivotKey);

        foreach ($this->pivotColumns as $column) {
            $builder->selectRaw($this->pivotTable . '.' . $column . ' as pivot_' . $column);
        }

        return $builder->get();
    }

    public function addConstraints(): void
    {
        $builder = $this->newQueryBuilder();

        $builder->join(
            $this->pivotTable,
            $this->getRelated()->getTable() . '.' . $this->relatedKey,
            '=',
            $this->pivotTable . '.' . $this->relatedPivotKey,
        );

        $builder->where(
            $this->pivotTable . '.' . $this->foreignPivotKey,
            '=',
            $this->parent->getAttribute($this->parentKey),
        );

        $builder->select($this->getRelated()->getTable() . '.*');
        $builder->selectRaw($this->pivotTable . '.' . $this->foreignPivotKey . ' as pivot_' . $this->foreignPivotKey);

        foreach ($this->pivotColumns as $column) {
            $builder->selectRaw($this->pivotTable . '.' . $column . ' as pivot_' . $column);
        }
    }

    /** @param Collection<Model> $models */
    public function addEagerConstraints(Collection $models): void
    {
        $keys = [];
        foreach ($models as $model) {
            $keys[] = $model->getAttribute($this->parentKey);
        }

        $builder = $this->newEagerQueryBuilder();

        $builder->join(
            $this->pivotTable,
            $this->getRelated()->getTable() . '.' . $this->relatedKey,
            '=',
            $this->pivotTable . '.' . $this->relatedPivotKey,
        );

        $builder->whereIn(
            $this->pivotTable . '.' . $this->foreignPivotKey,
            array_values(array_unique(array_filter($keys))),
        );

        $builder->select($this->getRelated()->getTable() . '.*');
        $builder->selectRaw($this->pivotTable . '.' . $this->foreignPivotKey . ' as pivot_' . $this->foreignPivotKey);

        foreach ($this->pivotColumns as $column) {
            $builder->selectRaw($this->pivotTable . '.' . $column . ' as pivot_' . $column);
        }
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
            $pivotKey = $result->getAttribute('pivot_' . $this->foreignPivotKey)
                ?? $result->getAttribute($this->foreignPivotKey);

            if ($pivotKey !== null) {
                $dictionary[$pivotKey][] = $result;
            }
        }

        foreach ($models as $model) {
            $key = $model->getAttribute($this->parentKey);
            $model->setRelation($relation, $this->related->newCollection($dictionary[$key] ?? []));
        }

        return $models;
    }

    /**
     * @param list<string> $columns
     */
    public function withPivot(array $columns): static
    {
        $this->pivotColumns = array_merge($this->pivotColumns, $columns);
        return $this;
    }

    public function withTimestamps(): static
    {
        return $this->withPivot(['created_at', 'updated_at']);
    }

    /**
     * Attach one or more related models by ID.
     *
     * @param int|string|list<int|string> $ids
     */
    public function attach(int|string|array $ids): void
    {
        $ids = (array) $ids;
        $now = date('Y-m-d H:i:s');

        foreach ($ids as $id) {
            $data = [
                $this->foreignPivotKey => $this->parent->getAttribute($this->parentKey),
                $this->relatedPivotKey => $id,
            ];

            if (in_array('created_at', $this->pivotColumns, true)) {
                $data['created_at'] = $now;
            }
            if (in_array('updated_at', $this->pivotColumns, true)) {
                $data['updated_at'] = $now;
            }

            $this->getConnection()->table($this->pivotTable)->insert($data);
        }
    }

    /**
     * Detach one or more related models by ID.
     * If no IDs given, detaches all.
     *
     * @param int|string|list<int|string>|null $ids
     */
    public function detach(int|string|array|null $ids = null): int
    {
        $query = $this->getConnection()->table($this->pivotTable)
            ->where($this->foreignPivotKey, '=', $this->parent->getAttribute($this->parentKey));

        if ($ids !== null) {
            $ids = (array) $ids;
            $query->whereIn($this->relatedPivotKey, $ids);
        }

        return $query->delete();
    }

    /**
     * @param list<int|string> $ids
     * @return array{attached: list<int|string>, detached: list<int|string>}
     */
    public function sync(array $ids): array
    {
        $current = $this->getConnection()->table($this->pivotTable)
            ->where($this->foreignPivotKey, '=', $this->parent->getAttribute($this->parentKey))
            ->pluck($this->relatedPivotKey)
            ->all();

        $current = array_map(fn ($v) => (int) $v, $current);
        $ids = array_map(fn ($v) => (int) $v, $ids);

        $detach = array_values(array_diff($current, $ids));
        $attach = array_values(array_diff($ids, $current));

        $this->detach($detach);
        $this->attach($attach);

        return [
            'attached' => $attach,
            'detached' => $detach,
        ];
    }

    /**
     * @param list<int|string> $ids
     * @return array{attached: list<int|string>, detached: list<int|string>}
     */
    public function toggle(array $ids): array
    {
        $current = $this->getConnection()->table($this->pivotTable)
            ->where($this->foreignPivotKey, '=', $this->parent->getAttribute($this->parentKey))
            ->pluck($this->relatedPivotKey)
            ->all();

        $current = array_map(fn ($v) => (int) $v, $current);

        $detach = array_values(array_intersect($ids, $current));
        $attach = array_values(array_diff($ids, $current));

        $this->detach($detach);
        $this->attach($attach);

        return [
            'attached' => $attach,
            'detached' => $detach,
        ];
    }

    /**
     * Check if an ID is currently attached.
     */
    public function isAttached(int|string $id): bool
    {
        return (bool) $this->getConnection()->table($this->pivotTable)
            ->where($this->foreignPivotKey, '=', $this->parent->getAttribute($this->parentKey))
            ->where($this->relatedPivotKey, '=', $id)
            ->exists();
    }
}
