<?php

declare(strict_types=1);

namespace Anvyr\Loom\Database;

use Anvyr\Loom\Contracts\CacheDriver;

class ModelBuilder extends QueryBuilder
{
    private Model $model;

    /** @var list<string> */
    private array $eagerLoads = [];

    /** @var array<class-string<Scope>, true> */
    private array $removedScopes = [];

    private bool $withoutHydration = false;
    private bool $scopesApplied = false;

    public function __construct(
        Connection $connection,
        Model $model,
        ?CacheDriver $cache = null,
    ) {
        parent::__construct($connection, $cache);
        $this->model = $model;
        $this->table($model->getTable());
    }

    // ── Terminal Methods (override QueryBuilder) ─────────────

    /** @return Collection<Model> */
    public function get(): Collection
    {
        $this->applyScopes();

        $results = parent::get();

        if ($this->withoutHydration) {
            return $results;
        }

        $models = [];

        foreach ($results as $row) {
            $models[] = $this->model->newFromBuilder((array) $row);
        }

        $collection = $this->model->newCollection($models);

        if (!empty($this->eagerLoads)) {
            $this->eagerLoadRelations($collection);
        }

        return $collection;
    }

    /** @return Model|null */
    public function first(): mixed
    {
        $this->applyScopes();
        $this->limit(1);

        // Call QueryBuilder::get() directly to avoid our overridden get()
        $results = parent::get();
        $row = $results->first();

        if ($row === null) {
            return null;
        }

        $model = $this->model->newFromBuilder((array) $row);

        if (!empty($this->eagerLoads)) {
            $this->eagerLoadRelations($this->model->newCollection([$model]));
        }

        return $model;
    }

    /** @return Model|null */
    public function find(int|string $id): mixed
    {
        return $this->where($this->model->getKeyName(), '=', $id)->first();
    }

    public function count(): int
    {
        $this->applyScopes();

        $originalSelects = $this->getSelects();
        $this->setSelects(['COUNT(*) as count']);
        $this->limit(1);

        $results = parent::get();
        $row = $results->first();

        $this->setSelects($originalSelects);

        if ($row === null) {
            return 0;
        }

        return (int) ($row['count'] ?? 0);
    }

    public function exists(): bool
    {
        $this->applyScopes();

        $originalSelects = $this->getSelects();
        $this->setSelects(['1']);
        $this->limit(1);

        $result = parent::first();
        $this->setSelects($originalSelects);

        return $result !== null;
    }

    /**
     * @return array{data: Collection<Model>, total: int, per_page: int, current_page: int, last_page: int}
     */
    public function paginate(int $perPage = 15, int $page = 1): array
    {
        $this->applyScopes();

        $page = max(1, $page);
        $perPage = max(1, $perPage);

        // Count query — temporarily disable hydration so get() returns raw arrays
        $countQuery = clone $this;
        $countQuery->withoutHydration = true;
        $countQuery->setSelects(['COUNT(*) as aggregate']);
        $countQuery->setOrderByClauses([]);
        $countQuery->setLimitValue(null);
        $countQuery->setOffsetValue(null);

        $countRow = $countQuery->get()->first();
        $total = (int) (($countRow['aggregate'] ?? null) ?? 0);

        // Data query — uses our overridden get() which returns Models
        $results = $this->offset(($page - 1) * $perPage)->limit($perPage)->get();

        return [
            'data' => $results,
            'total' => $total,
            'per_page' => $perPage,
            'current_page' => $page,
            'last_page' => max(1, (int) ceil($total / $perPage)),
        ];
    }

    // ── Eager Loading ────────────────────────────────────────

    /**
     * @param string|list<string> $relations
     */
    public function with(string|array $relations): static
    {
        $this->eagerLoads = array_merge($this->eagerLoads, (array) $relations);
        return $this;
    }

    /**
     * @param Collection<Model> $models
     * @return Collection<Model>
     */
    private function eagerLoadRelations(Collection $models): Collection
    {
        foreach ($this->eagerLoads as $name) {
            if ($models->isEmpty()) {
                break;
            }

            $relation = $this->getRelationInstance($name);
            $relation->addEagerConstraints($models);
            $results = $relation->getEager();
            $relation->match($models, $results, $name);
        }

        return $models;
    }

    private function getRelationInstance(string $name): \Anvyr\Loom\Database\Relations\Relation
    {
        if (!method_exists($this->model, $name)) {
            throw new \RuntimeException("Relation '{$name}' is not defined on " . \get_class($this->model) . '.');
        }

        $relation = $this->model->{$name}();

        if (!$relation instanceof \Anvyr\Loom\Database\Relations\Relation) {
            throw new \RuntimeException("Method '{$name}' on " . \get_class($this->model) . ' does not return a Relation instance.');
        }

        return $relation;
    }

    // ── Global Scopes ────────────────────────────────────────

    /**
     * @param class-string<Scope> $scopeClass
     */
    public function removeGlobalScope(string $scopeClass): static
    {
        $this->removedScopes[$scopeClass] = true;
        return $this;
    }

    /**
     * @param class-string<Scope> $scopeClass
     */
    public function withoutGlobalScope(string $scopeClass): static
    {
        return $this->removeGlobalScope($scopeClass);
    }

    /** @return array<class-string<Scope>, true> */
    public function getRemovedScopes(): array
    {
        return $this->removedScopes;
    }

    // ── Scope Forwarding ─────────────────────────────────────

    /**
     * @param array<int, mixed> $parameters
     */
    public function __call(string $method, array $parameters): mixed
    {
        $scopeMethod = 'scope' . ucfirst($method);

        if (method_exists($this->model, $scopeMethod)) {
            $this->model->{$scopeMethod}($this, ...$parameters);
            return $this;
        }

        return parent::__call($method, $parameters);
    }

    private function applyScopes(): void
    {
        if ($this->scopesApplied) {
            return;
        }

        $this->scopesApplied = true;

        foreach ($this->model->getGlobalScopes() as $key => $scope) {
            if (!isset($this->removedScopes[$key])) {
                $scope->apply($this, $this->model);
            }
        }
    }

    // ── Model Access ─────────────────────────────────────────

    public function getModel(): Model
    {
        return $this->model;
    }
}
