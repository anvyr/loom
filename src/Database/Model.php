<?php

declare(strict_types=1);

namespace Anvyr\Loom\Database;

use Anvyr\Loom\Contracts\ModelInterface;
use Anvyr\Loom\Core\Application;
use Anvyr\Loom\Database\Concerns\HasAttributes;
use Anvyr\Loom\Database\Concerns\HasRelationships;
use Anvyr\Loom\Support\Str;
use JsonSerializable;

class Model implements ModelInterface, JsonSerializable
{
    use HasAttributes;
    use HasRelationships;

    /** @var (\Closure(): Connection)|null */
    private static ?\Closure $connectionResolver = null;

    /** @var array<class-string, true> */
    private static array $booted = [];

    /** @var array<class-string<Model>, array<class-string<Scope>, Scope>> */
    private static array $allGlobalScopes = [];

    protected ?string $table = null;
    protected string $primaryKey = 'id';
    protected ?string $connection = null;
    protected bool $timestamps = true;

    /** @var list<string> */
    protected array $fillable = [];

    /** @var list<string> */
    protected array $guarded = ['*'];

    protected bool $exists = false;

    /**
     * @param array<string, mixed> $attributes
     */
    public function __construct(array $attributes = [])
    {
        $this->bootIfNotBooted();
        $this->initializeTraits();
        $this->fill($attributes);
    }

    // ── Connection ───────────────────────────────────────────

    /**
     * @param \Closure(): Connection $resolver
     */
    public static function setConnectionResolver(\Closure $resolver): void
    {
        self::$connectionResolver = $resolver;
    }

    public function getConnection(): Connection
    {
        if (self::$connectionResolver === null) {
            throw new \RuntimeException('Model connection resolver has not been set. Register it in CoreServiceProvider.');
        }

        return (self::$connectionResolver)();
    }

    public function getConnectionName(): ?string
    {
        return $this->connection;
    }

    // ── Table & Key ──────────────────────────────────────────

    public function getTable(): string
    {
        if ($this->table !== null) {
            return $this->table;
        }

        return Str::plural(Str::snake(self::classBasename(static::class)));
    }

    public function getKeyName(): string
    {
        return $this->primaryKey;
    }

    public function getKey(): int|string|null
    {
        return $this->getAttribute($this->getKeyName());
    }

    // ── Timestamps ───────────────────────────────────────────

    public function getCreatedAtColumn(): string
    {
        return 'created_at';
    }

    public function getUpdatedAtColumn(): string
    {
        return 'updated_at';
    }

    protected function usesTimestamps(): bool
    {
        return $this->timestamps;
    }

    // ── Mass Assignment ──────────────────────────────────────

    /**
     * @param array<string, mixed> $attributes
     */
    public function fill(array $attributes): static
    {
        foreach ($attributes as $key => $value) {
            if ($this->isFillable($key)) {
                $this->setAttribute($key, $value);
            }
        }

        return $this;
    }

    protected function isFillable(string $key): bool
    {
        if (in_array($key, $this->fillable, true)) {
            return true;
        }

        if (in_array('*', $this->guarded, true)) {
            return false;
        }

        return !in_array($key, $this->guarded, true);
    }

    // ── CRUD ─────────────────────────────────────────────────

    public function save(): bool
    {
        if ($this->exists) {
            return $this->performUpdate();
        }

        return $this->performInsert();
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public static function create(array $attributes = []): static
    {
        $model = new static($attributes);
        $model->save();
        return $model;
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public function update(array $attributes = []): bool
    {
        $this->fill($attributes);
        return $this->save();
    }

    public function delete(): bool
    {
        if (!$this->exists) {
            return false;
        }

        $this->fireModelEvent('deleting');

        $deleted = $this->newQueryWithoutScopes()
            ->where($this->getKeyName(), '=', $this->getKey())
            ->delete();

        $this->exists = false;

        $this->fireModelEvent('deleted');

        return $deleted > 0;
    }

    public function forceDelete(): bool
    {
        return $this->delete();
    }

    private function performInsert(): bool
    {
        $this->fireModelEvent('creating');

        $attributes = $this->getStorableAttributes();

        if ($this->usesTimestamps()) {
            $now = date('Y-m-d H:i:s');
            $createdCol = $this->getCreatedAtColumn();
            $updatedCol = $this->getUpdatedAtColumn();

            if (!array_key_exists($createdCol, $attributes) || $attributes[$createdCol] === null) {
                $attributes[$createdCol] = $now;
                $this->attributes[$createdCol] = $now;
            }
            if (!array_key_exists($updatedCol, $attributes) || $attributes[$updatedCol] === null) {
                $attributes[$updatedCol] = $now;
                $this->attributes[$updatedCol] = $now;
            }
        }

        $id = $this->newQueryWithoutScopes()
            ->table($this->getTable())
            ->insertGetId($attributes);

        $this->setAttribute($this->getKeyName(), $id);
        $this->exists = true;
        $this->syncOriginal();

        $this->fireModelEvent('created');

        return true;
    }

    private function performUpdate(): bool
    {
        $dirty = $this->getDirty();

        if (empty($dirty)) {
            return true;
        }

        $this->fireModelEvent('updating');

        if ($this->usesTimestamps()) {
            $dirty[$this->getUpdatedAtColumn()] = date('Y-m-d H:i:s');
        }

        $this->newQueryWithoutScopes()
            ->where($this->getKeyName(), '=', $this->getKey())
            ->update($this->prepareForStorage($dirty));

        $this->syncOriginal();

        $this->fireModelEvent('updated');

        return true;
    }

    /**
     * Get attributes prepared for database storage (encode JSON casts, etc.).
     * @param array<string, mixed> $attributes
     * @return array<string, mixed>
     */
    private function prepareForStorage(array $attributes): array
    {
        foreach ($attributes as $key => $value) {
            if ($this->hasCast($key)) {
                $cast = $this->casts[$key];
                if (($cast === 'json' || $cast === 'array') && is_array($value)) {
                    $attributes[$key] = json_encode($value);
                }
            }
        }

        return $attributes;
    }

    /**
     * Get all attributes prepared for initial storage (INSERT).
     * @return array<string, mixed>
     */
    private function getStorableAttributes(): array
    {
        return $this->prepareForStorage($this->attributes);
    }

    // ── Events ───────────────────────────────────────────────

    protected function fireModelEvent(string $event): void
    {
        if (!Application::hasInstance()) {
            return;
        }

        $dispatcher = Application::getInstance()->events;
        $dispatcher->dispatch('model.' . $event, $this);
    }

    // ── Query Building ───────────────────────────────────────

    public function newQuery(): ModelBuilder
    {
        return $this->newModelBuilder();
    }

    public function newQueryWithoutScopes(): ModelBuilder
    {
        return $this->newModelBuilder();
    }

    public static function query(): ModelBuilder
    {
        return (new static())->newQuery();
    }

    protected function newModelBuilder(): ModelBuilder
    {
        return new ModelBuilder($this->getConnection(), $this);
    }

    protected function applyGlobalScopes(ModelBuilder $builder): void
    {
        foreach ($this->getGlobalScopes() as $scope) {
            $scope->apply($builder, $this);
        }
    }

    // ── Hydration ────────────────────────────────────────────

    /**
     * @param array<string, mixed> $attributes
     */
    public function newFromBuilder(array $attributes = []): static
    {
        $model = $this->newInstance([], true);
        $model->setRawAttributes($attributes, true);
        return $model;
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public function newInstance(array $attributes = [], bool $exists = false): static
    {
        $model = new static($attributes);
        $model->exists = $exists;
        return $model;
    }

    /**
     * @param list<Model> $models
     * @return Collection<Model>
     */
    public function newCollection(array $models = []): Collection
    {
        return new Collection($models);
    }

    // ── Global Scopes ────────────────────────────────────────

    public static function addGlobalScope(Scope $scope): void
    {
        $class = static::class;
        self::$allGlobalScopes[$class][\get_class($scope)] = $scope;
    }

    public static function hasGlobalScope(string $scopeClass): bool
    {
        return isset(self::$allGlobalScopes[static::class][$scopeClass]);
    }

    /** @return array<class-string<Scope>, Scope> */
    public function getGlobalScopes(): array
    {
        return self::$allGlobalScopes[static::class] ?? [];
    }

    // ── Serialization ────────────────────────────────────────

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $attributes = $this->attributesToArray();

        foreach ($this->relations as $key => $value) {
            if ($value instanceof Collection) {
                $attributes[$key] = $value->map(fn (Model $item) => $item->toArray())->all();
            } elseif ($value !== null) {
                $attributes[$key] = $value->toArray();
            } else {
                $attributes[$key] = null;
            }
        }

        return $attributes;
    }

    public function toJson(int $options = 0): string
    {
        return json_encode($this->toArray(), $options | JSON_THROW_ON_ERROR);
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    // ── Magic Methods ────────────────────────────────────────

    public function __get(string $key): mixed
    {
        if (array_key_exists($key, $this->relations)) {
            return $this->relations[$key];
        }

        if (method_exists($this, $key)) {
            return $this->getRelationValue($key);
        }

        return $this->getAttribute($key);
    }

    public function __set(string $key, mixed $value): void
    {
        $this->setAttribute($key, $value);
    }

    public function __isset(string $key): bool
    {
        if (array_key_exists($key, $this->relations)) {
            return $this->relations[$key] !== null;
        }

        if (method_exists($this, $key)) {
            return $this->getRelationValue($key) !== null;
        }

        return array_key_exists($key, $this->attributes);
    }

    public function __unset(string $key): void
    {
        unset($this->attributes[$key], $this->relations[$key]);
    }

    /**
     * @param array<int, mixed> $parameters
     */
    public function __call(string $method, array $parameters): mixed
    {
        $scopeMethod = 'scope' . ucfirst($method);

        if (method_exists($this, $scopeMethod)) {
            $builder = $this->newQuery();
            $this->{$scopeMethod}($builder, ...$parameters);
            return $builder;
        }

        return $this->newQuery()->{$method}(...$parameters);
    }

    /**
     * @param array<int, mixed> $parameters
     */
    public static function __callStatic(string $method, array $parameters): mixed
    {
        return static::query()->{$method}(...$parameters);
    }

    // ── Boot ─────────────────────────────────────────────────

    protected static function boot(): void
    {
        $class = static::class;

        foreach (self::classUsesRecursive($class) as $trait) {
            $method = 'boot' . self::classBasename($trait);

            if (method_exists($class, $method)) {
                $class::$method();
            }
        }
    }

    private function bootIfNotBooted(): void
    {
        $class = static::class;

        if (!isset(self::$booted[$class])) {
            self::$booted[$class] = true;
            static::boot();
        }
    }

    protected function initializeTraits(): void
    {
        foreach (self::classUsesRecursive(static::class) as $trait) {
            $method = 'initialize' . self::classBasename($trait);

            if (method_exists($this, $method)) {
                $this->{$method}();
            }
        }
    }

    // ── ModelInterface ───────────────────────────────────────

    /** @return array<string, mixed> */
    public function getAttributes(): array
    {
        return $this->attributes;
    }

    public function isNew(): bool
    {
        return !$this->exists;
    }

    public function exists(): bool
    {
        return $this->exists;
    }

    // ── String Representation ────────────────────────────────

    public function __toString(): string
    {
        return static::class . ' [' . ($this->getKey() ?? 'new') . ']';
    }

    /**
     * @param class-string $class
     * @return array<class-string, class-string>
     */
    private static function classUsesRecursive(string $class): array
    {
        $results = [];

        foreach (array_reverse((new \ReflectionClass($class))->getTraits()) as $trait) {
            $results[$trait->getName()] = $trait->getName();
            foreach ($trait->getTraits() as $nested) {
                $results[$nested->getName()] = $nested->getName();
            }
        }

        return $results;
    }

    private static function classBasename(string $class): string
    {
        $parts = explode('\\', $class);
        return end($parts);
    }
}
