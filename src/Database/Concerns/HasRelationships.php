<?php

declare(strict_types=1);

namespace Anvyr\Loom\Database\Concerns;

use Anvyr\Loom\Database\Collection;
use Anvyr\Loom\Database\Model;
use Anvyr\Loom\Database\Relations\BelongsTo;
use Anvyr\Loom\Database\Relations\BelongsToMany;
use Anvyr\Loom\Database\Relations\HasMany;
use Anvyr\Loom\Database\Relations\HasOne;
use Anvyr\Loom\Database\Relations\Relation;
use Anvyr\Loom\Support\Str;

/**
 * Manages relationship definitions, lazy loading, and eager loading storage.
 */
trait HasRelationships
{
    /** @var array<string, Model|Collection<Model>|null> */
    protected array $relations = [];

    /**
     * @param class-string<Model> $related
     */
    protected function hasOne(
        string $related,
        ?string $foreignKey = null,
        ?string $localKey = null,
    ): HasOne {
        return new HasOne(
            $this,
            $related,
            $foreignKey ?? $this->getForeignKey(),
            $localKey ?? $this->getKeyName(),
        );
    }

    /**
     * @param class-string<Model> $related
     */
    protected function hasMany(
        string $related,
        ?string $foreignKey = null,
        ?string $localKey = null,
    ): HasMany {
        return new HasMany(
            $this,
            $related,
            $foreignKey ?? $this->getForeignKey(),
            $localKey ?? $this->getKeyName(),
        );
    }

    /**
     * @param class-string<Model> $related
     */
    protected function belongsTo(
        string $related,
        ?string $foreignKey = null,
        ?string $ownerKey = null,
    ): BelongsTo {
        return new BelongsTo(
            $this,
            $related,
            $foreignKey ?? Str::snake(self::classBasename($related)) . '_id',
            $ownerKey ?? 'id',
        );
    }

    /**
     * @param class-string<Model> $related
     */
    protected function belongsToMany(
        string $related,
        ?string $pivotTable = null,
        ?string $foreignPivotKey = null,
        ?string $relatedPivotKey = null,
        ?string $parentKey = null,
        ?string $relatedKey = null,
    ): BelongsToMany {
        return new BelongsToMany(
            $this,
            $related,
            $pivotTable ?? $this->joiningTable($related),
            $foreignPivotKey ?? $this->getForeignKey(),
            $relatedPivotKey ?? Str::snake(self::classBasename($related)) . '_id',
            $parentKey ?? $this->getKeyName(),
            $relatedKey ?? 'id',
        );
    }

    public function getRelationValue(string $key): mixed
    {
        if (array_key_exists($key, $this->relations)) {
            return $this->relations[$key];
        }

        if (method_exists($this, $key)) {
            $relation = $this->{$key}();

            if ($relation instanceof Relation) {
                $results = $relation->getResults();
                $this->relations[$key] = $results;
                return $results;
            }
        }

        return null;
    }

    public function setRelation(string $key, mixed $value): static
    {
        $this->relations[$key] = $value;
        return $this;
    }

    public function getRelation(string $key): mixed
    {
        return $this->relations[$key] ?? null;
    }

    public function relationLoaded(string $key): bool
    {
        return array_key_exists($key, $this->relations);
    }

    public function unsetRelation(string $key): static
    {
        unset($this->relations[$key]);
        return $this;
    }

    public function getForeignKey(): string
    {
        return Str::snake(self::classBasename(static::class)) . '_id';
    }

    /**
     * @param class-string<Model> $related
     */
    protected function joiningTable(string $related): string
    {
        $models = [
            Str::snake(self::classBasename($related)),
            Str::snake(self::classBasename(static::class)),
        ];
        sort($models);

        return implode('_', $models);
    }

    protected function initializeHasRelationships(): void
    {
    }

    private static function classBasename(string $class): string
    {
        $parts = explode('\\', $class);
        return end($parts);
    }
}
