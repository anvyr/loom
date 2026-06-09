<?php

declare(strict_types=1);

namespace Anvyr\Loom\Database;

use Anvyr\Loom\Contracts\CacheDriver;

/**
 * @phpstan-type Row array<string, mixed>
 * @phpstan-type WhereClause array<string, mixed>
 * @phpstan-type JoinClause array{type: 'INNER'|'LEFT'|'RIGHT', table: string, first: string, operator: string, second: string}
 * @phpstan-type HavingClause array{column: string, operator: string, value: mixed, boolean: 'AND'|'OR'}
 * @phpstan-type OrderByClause array{raw: string, direction: ''}|array{column: string, direction: 'ASC'|'DESC'}
 */
class QueryBuilder
{
    private string $table;

    /** @var list<WhereClause> */
    private array $wheres = [];

    /** @var list<mixed> */
    private array $bindings = [];

    /** @var list<string> */
    private array $selects = ['*'];

    /** @var list<JoinClause> */
    private array $joins = [];

    /** @var list<string> */
    private array $groupBy = [];

    /** @var list<HavingClause> */
    private array $having = [];

    /** @var list<OrderByClause> */
    private array $orderByClauses = [];
    private ?int $limitValue = null;
    private ?int $offsetValue = null;
    private ?int $cacheTtl = null;

    public function __construct(
        private readonly Connection $connection,
        private readonly ?CacheDriver $cache = null
    ) {
    }

    public function table(string $table): static
    {
        $this->assertTableReference($table);
        $this->table = $table;
        return $this;
    }

    /** @param string|list<string|RawExpression>|RawExpression $columns */
    public function select(string|array|RawExpression $columns = '*'): static
    {
        if ($columns instanceof RawExpression) {
            $this->selects = [$columns->getValue()];
            $this->bindings = array_merge($this->bindings, $columns->getBindings());
        } elseif (is_array($columns)) {
            $this->selects = array_map(function ($col) {
                if ($col instanceof RawExpression) {
                    $this->bindings = array_merge($this->bindings, $col->getBindings());
                    return $col->getValue();
                }
                return $col;
            }, $columns);
        } else {
            $this->selects = func_get_args();
        }
        return $this;
    }

    /** @param list<mixed> $bindings */
    public function selectRaw(string $expression, array $bindings = []): static
    {
        $this->selects[] = $expression;
        $this->bindings = array_merge($this->bindings, $bindings);
        return $this;
    }

    public function where(string|RawExpression $column, string $operator, mixed $value): static
    {
        $this->assertOperator($operator);

        $columnSql = $column instanceof RawExpression ? $column->getValue() : $column;
        if (!$column instanceof RawExpression) {
            $this->assertColumnReference($columnSql);
        }

        $this->wheres[] = [
            'type' => 'basic',
            'column' => $columnSql,
            'operator' => $operator,
            'value' => $value,
            'boolean' => 'AND',
        ];

        if ($column instanceof RawExpression) {
            $this->bindings = array_merge($this->bindings, $column->getBindings());
        }
        $this->bindings[] = $value;

        return $this;
    }

    /** @param list<mixed> $bindings */
    public function whereRaw(string $expression, array $bindings = []): static
    {
        $this->wheres[] = [
            'type' => 'raw',
            'sql' => $expression,
            'boolean' => 'AND',
        ];

        $this->bindings = array_merge($this->bindings, $bindings);

        return $this;
    }

    public function orWhere(string $column, string $operator, mixed $value): static
    {
        $this->assertColumnReference($column);
        $this->assertOperator($operator);

        $this->wheres[] = [
            'type' => 'basic',
            'column' => $column,
            'operator' => $operator,
            'value' => $value,
            'boolean' => 'OR',
        ];

        $this->bindings[] = $value;

        return $this;
    }

    /** @param list<mixed> $values */
    public function whereIn(string $column, array $values): static
    {
        $this->assertColumnReference($column);

        $this->wheres[] = [
            'type' => 'in',
            'column' => $column,
            'values' => $values,
            'boolean' => 'AND',
        ];

        foreach ($values as $value) {
            $this->bindings[] = $value;
        }

        return $this;
    }

    public function whereNull(string $column): static
    {
        $this->assertColumnReference($column);

        $this->wheres[] = [
            'type' => 'null',
            'column' => $column,
            'boolean' => 'AND',
        ];

        return $this;
    }

    public function whereNotNull(string $column): static
    {
        $this->assertColumnReference($column);

        $this->wheres[] = [
            'type' => 'not_null',
            'column' => $column,
            'boolean' => 'AND',
        ];

        return $this;
    }

    public function join(string $table, string $first, string $operator, string $second): static
    {
        $this->assertTableReference($table);
        $this->assertColumnReference($first);
        $this->assertColumnReference($second);
        $this->assertOperator($operator);

        $this->joins[] = [
            'type' => 'INNER',
            'table' => $table,
            'first' => $first,
            'operator' => $operator,
            'second' => $second,
        ];

        return $this;
    }

    public function leftJoin(string $table, string $first, string $operator, string $second): static
    {
        $this->assertTableReference($table);
        $this->assertColumnReference($first);
        $this->assertColumnReference($second);
        $this->assertOperator($operator);

        $this->joins[] = [
            'type' => 'LEFT',
            'table' => $table,
            'first' => $first,
            'operator' => $operator,
            'second' => $second,
        ];

        return $this;
    }

    public function rightJoin(string $table, string $first, string $operator, string $second): static
    {
        $this->assertTableReference($table);
        $this->assertColumnReference($first);
        $this->assertColumnReference($second);
        $this->assertOperator($operator);

        // SQLite does not support RIGHT JOIN - convert to LEFT JOIN with swapped tables
        if ($this->connection->getDriver() === 'sqlite') {
            // Swap: "A RIGHT JOIN B ON A.x = B.y" becomes "B LEFT JOIN A ON B.y = A.x"
            $originalTable = $this->table;
            $this->table = $table;

            return $this->leftJoin($originalTable, $second, $operator, $first);
        }

        $this->joins[] = [
            'type' => 'RIGHT',
            'table' => $table,
            'first' => $first,
            'operator' => $operator,
            'second' => $second,
        ];

        return $this;
    }

    public function groupBy(string ...$columns): static
    {
        foreach ($columns as $column) {
            $this->assertColumnReference($column);
        }

        $this->groupBy = array_merge($this->groupBy, array_values($columns));
        return $this;
    }

    public function having(string $column, string $operator, mixed $value): static
    {
        $this->assertColumnReference($column);
        $this->assertOperator($operator);

        $this->having[] = [
            'column' => $column,
            'operator' => $operator,
            'value' => $value,
            'boolean' => 'AND',
        ];

        $this->bindings[] = $value;

        return $this;
    }

    public function orderBy(string|RawExpression $column, string $direction = 'ASC'): static
    {
        if ($column instanceof RawExpression) {
            $this->orderByClauses[] = ['raw' => $column->getValue(), 'direction' => ''];
            $this->bindings = array_merge($this->bindings, $column->getBindings());
        } else {
            $this->assertColumnReference($column);
            $direction = strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC';
            $this->orderByClauses[] = ['column' => $column, 'direction' => $direction];
        }
        return $this;
    }

    /** @param list<mixed> $bindings */
    public function orderByRaw(string $expression, array $bindings = []): static
    {
        $this->orderByClauses[] = ['raw' => $expression, 'direction' => ''];
        $this->bindings = array_merge($this->bindings, $bindings);
        return $this;
    }

    public function limit(int $limit): static
    {
        $this->limitValue = $limit;
        return $this;
    }

    public function offset(int $offset): static
    {
        $this->offsetValue = $offset;
        return $this;
    }

    public function cache(int $ttl = 300): static
    {
        $this->cacheTtl = $ttl;
        return $this;
    }

    /** @return list<string> */
    public function getSelects(): array
    {
        return $this->selects;
    }

    /** @param list<string> $selects */
    public function setSelects(array $selects): static
    {
        $this->selects = $selects;
        return $this;
    }

    /** @param list<OrderByClause> $clauses */
    public function setOrderByClauses(array $clauses): static
    {
        $this->orderByClauses = $clauses;
        return $this;
    }

    public function setLimitValue(?int $limit): static
    {
        $this->limitValue = $limit;
        return $this;
    }

    public function setOffsetValue(?int $offset): static
    {
        $this->offsetValue = $offset;
        return $this;
    }

    /** @return Collection<Row> */
    public function get(): Collection
    {
        $sql = $this->toSql();
        $cacheKey = null;

        if ($this->cacheTtl && $this->cache) {
            try {
                $cacheKey = 'query:' . md5($sql . serialize($this->bindings));
                $cached = $this->cache->get($cacheKey);

                if ($cached !== null) {
                    return new Collection($cached);
                }
            } catch (\Throwable $e) {
                // Cache read failed, continue to database query
            }
        }

        $results = $this->connection->query($sql, $this->bindings);

        if ($this->cacheTtl && $this->cache && $cacheKey !== null) {
            try {
                $this->cache->set($cacheKey, $results, $this->cacheTtl);
            } catch (\Throwable $e) {
                // Cache write failed, but we have results from DB
            }
        }

        return new Collection($results);
    }

    /** @return Row|null */
    public function first(): mixed
    {
        $this->limit(1);
        $results = $this->get();
        return $results->first();
    }

    /** @return Collection<mixed> */
    public function pluck(string $column): Collection
    {
        $results = $this->select($column)->get();

        return $results->map(function ($row) use ($column) {
            return $row[$column] ?? null;
        });
    }

    /** @return Row|null */
    public function find(int|string $id): mixed
    {
        return $this->where('id', '=', $id)->first();
    }

    public function count(): int
    {
        $originalSelects = $this->selects;
        $this->selects = ['COUNT(*) as count'];

        $result = $this->first();
        $this->selects = $originalSelects;

        return (int) ($result['count'] ?? 0);
    }

    public function exists(): bool
    {
        $originalSelects = $this->selects;
        $this->selects = ['1'];
        $this->limit(1);

        $result = $this->first();
        $this->selects = $originalSelects;

        return $result !== null;
    }

    /**
     * @return array{data: Collection<Row>, total: int, per_page: int, current_page: int, last_page: int}
     */
    public function paginate(int $perPage = 15, int $page = 1): array
    {
        $page = max(1, $page);
        $perPage = max(1, $perPage);

        $countQuery = clone $this;
        $countQuery->selects = ['COUNT(*) as aggregate'];
        $countQuery->orderByClauses = [];
        $countQuery->limitValue = null;
        $countQuery->offsetValue = null;

        $countResult = $countQuery->get()->first();
        $total = (int) ($countResult['aggregate'] ?? 0);

        $dataQuery = clone $this;
        $dataQuery->limitValue = $perPage;
        $dataQuery->offsetValue = ($page - 1) * $perPage;

        return [
            'data' => $dataQuery->get(),
            'total' => $total,
            'per_page' => $perPage,
            'current_page' => $page,
            'last_page' => max(1, (int) ceil($total / $perPage)),
        ];
    }

    /** @param Row $data */
    public function insert(array $data): bool
    {
        foreach (array_keys($data) as $column) {
            $this->assertColumnReference((string) $column);
        }

        $columns = array_keys($data);
        $placeholders = array_fill(0, count($columns), '?');

        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $this->table,
            implode(', ', $columns),
            implode(', ', $placeholders)
        );

        $this->connection->statement($sql, array_values($data));
        return true;
    }

    /** @param Row $data */
    public function insertGetId(array $data): int
    {
        if ($this->connection->getDriver() === 'pgsql') {
            foreach (array_keys($data) as $column) {
                $this->assertColumnReference((string) $column);
            }

            $columns = array_keys($data);
            $placeholders = array_fill(0, count($columns), '?');

            $sql = sprintf(
                'INSERT INTO %s (%s) VALUES (%s) RETURNING id',
                $this->table,
                implode(', ', $columns),
                implode(', ', $placeholders)
            );

            $rows = $this->connection->query($sql, array_values($data));
            return (int) ($rows[0]['id'] ?? 0);
        }

        $this->insert($data);
        return (int) $this->connection->lastInsertId();
    }

    /**
     * @param Row $data
     * @param string|list<string> $uniqueBy
     * @param list<string>|null $update
     */
    public function upsert(array $data, string|array $uniqueBy, ?array $update = null): bool
    {
        foreach (array_keys($data) as $column) {
            $this->assertColumnReference((string) $column);
        }

        $uniqueBy = (array) $uniqueBy;
        foreach ($uniqueBy as $column) {
            $this->assertColumnReference((string) $column);
        }

        if ($data === []) {
            throw new \InvalidArgumentException('Upsert data cannot be empty.');
        }

        $columns = array_map('strval', array_keys($data));
        $placeholders = array_fill(0, count($columns), '?');
        $bindings = array_values($data);

        // Determine which columns to update on conflict
        $updateColumns = array_values($update ?? array_diff($columns, $uniqueBy));
        foreach ($updateColumns as $column) {
            $this->assertColumnReference((string) $column);
        }

        $driver = $this->connection->getDriver();

        /** @var non-empty-list<string> $columns */
        $sql = match ($driver) {
            'sqlite' => $this->buildSqliteUpsert($columns, $placeholders, $uniqueBy, $updateColumns),
            'mysql' => $this->buildMysqlUpsert($columns, $placeholders, $updateColumns),
            'pgsql' => $this->buildPostgresUpsert($columns, $placeholders, $uniqueBy, $updateColumns),
            default => throw new \RuntimeException("Upsert not supported for driver: {$driver}")
        };

        // MySQL ON DUPLICATE KEY UPDATE needs values twice (for INSERT and UPDATE)
        if ($driver === 'mysql' && !empty($updateColumns)) {
            foreach ($updateColumns as $col) {
                $bindings[] = $data[$col];
            }
        }

        $this->connection->statement($sql, $bindings);
        return true;
    }

    /**
     * @param list<string> $columns
     * @param list<string> $placeholders
     * @param list<string> $uniqueBy
     * @param list<string> $updateColumns
     */
    private function buildSqliteUpsert(array $columns, array $placeholders, array $uniqueBy, array $updateColumns): string
    {
        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s) ON CONFLICT (%s)',
            $this->table,
            implode(', ', $columns),
            implode(', ', $placeholders),
            implode(', ', $uniqueBy)
        );

        if (empty($updateColumns)) {
            return $sql . ' DO NOTHING';
        }

        $updates = array_map(fn ($col) => "{$col} = excluded.{$col}", $updateColumns);
        return $sql . ' DO UPDATE SET ' . implode(', ', $updates);
    }

    /**
     * @param non-empty-list<string> $columns
     * @param list<string> $placeholders
     * @param list<string> $updateColumns
     */
    private function buildMysqlUpsert(array $columns, array $placeholders, array $updateColumns): string
    {
        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $this->table,
            implode(', ', $columns),
            implode(', ', $placeholders)
        );

        if (empty($updateColumns)) {
            // MySQL doesn't have "DO NOTHING", use a no-op update
            $firstCol = $columns[0];
            return $sql . " ON DUPLICATE KEY UPDATE {$firstCol} = {$firstCol}";
        }

        $updates = array_map(fn ($col) => "{$col} = ?", $updateColumns);
        return $sql . ' ON DUPLICATE KEY UPDATE ' . implode(', ', $updates);
    }

    /**
     * @param list<string> $columns
     * @param list<string> $placeholders
     * @param list<string> $uniqueBy
     * @param list<string> $updateColumns
     */
    private function buildPostgresUpsert(array $columns, array $placeholders, array $uniqueBy, array $updateColumns): string
    {
        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s) ON CONFLICT (%s)',
            $this->table,
            implode(', ', $columns),
            implode(', ', $placeholders),
            implode(', ', $uniqueBy)
        );

        if (empty($updateColumns)) {
            return $sql . ' DO NOTHING';
        }

        $updates = array_map(fn ($col) => "{$col} = EXCLUDED.{$col}", $updateColumns);
        return $sql . ' DO UPDATE SET ' . implode(', ', $updates);
    }

    /** @param Row $data */
    public function update(array $data): int
    {
        $sets = [];
        $bindings = [];

        foreach ($data as $column => $value) {
            $this->assertColumnReference((string) $column);
            $sets[] = "{$column} = ?";
            $bindings[] = $value;
        }

        $sql = sprintf(
            'UPDATE %s SET %s%s',
            $this->table,
            implode(', ', $sets),
            $this->buildWhere()
        );

        return $this->connection->statement($sql, array_merge($bindings, $this->bindings));
    }

    public function delete(): int
    {
        $sql = sprintf(
            'DELETE FROM %s%s',
            $this->table,
            $this->buildWhere()
        );

        return $this->connection->statement($sql, $this->bindings);
    }

    public function toSql(): string
    {
        $sql = sprintf(
            'SELECT %s FROM %s',
            implode(', ', $this->selects),
            $this->table
        );

        $sql .= $this->buildJoins();
        $sql .= $this->buildWhere();
        $sql .= $this->buildGroupBy();
        $sql .= $this->buildHaving();
        $sql .= $this->buildOrderBy();

        if ($this->limitValue !== null) {
            $sql .= " LIMIT {$this->limitValue}";
        }

        if ($this->offsetValue !== null) {
            $sql .= " OFFSET {$this->offsetValue}";
        }

        return $sql;
    }

    private function assertOperator(string $operator): void
    {
        if (!preg_match('/^[A-Za-z<>=!]+$/', $operator)) {
            throw new \InvalidArgumentException("Invalid SQL operator: {$operator}");
        }
    }

    private function assertColumnReference(string $column): void
    {
        if ($column === '' || !preg_match('/^(?:\*|[A-Za-z_][A-Za-z0-9_]*)(?:\.(?:\*|[A-Za-z_][A-Za-z0-9_]*))*$/', $column)) {
            throw new \InvalidArgumentException("Invalid column reference: {$column}. Use RawExpression for complex clauses.");
        }
    }

    private function assertTableReference(string $table): void
    {
        $table = trim($table);
        if ($table === '') {
            throw new \InvalidArgumentException('Table name cannot be empty.');
        }

        $parts = preg_split('/\s+as\s+|\s+/i', $table);
        if ($parts === false || count($parts) > 2) {
            throw new \InvalidArgumentException("Invalid table reference: {$table}");
        }

        $base = $parts[0];
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*(?:\.[A-Za-z_][A-Za-z0-9_]*)*$/', $base)) {
            throw new \InvalidArgumentException("Invalid table reference: {$table}");
        }

        if (isset($parts[1]) && !preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $parts[1])) {
            throw new \InvalidArgumentException("Invalid table alias in reference: {$table}");
        }
    }

    private function buildJoins(): string
    {
        if (empty($this->joins)) {
            return '';
        }

        $sql = '';
        foreach ($this->joins as $join) {
            $sql .= sprintf(
                ' %s JOIN %s ON %s %s %s',
                $join['type'],
                $join['table'],
                $join['first'],
                $join['operator'],
                $join['second']
            );
        }

        return $sql;
    }

    private function buildGroupBy(): string
    {
        if (empty($this->groupBy)) {
            return '';
        }

        return ' GROUP BY ' . implode(', ', $this->groupBy);
    }

    private function buildHaving(): string
    {
        if (empty($this->having)) {
            return '';
        }

        $sql = ' HAVING ';
        $clauses = [];

        foreach ($this->having as $index => $condition) {
            $boolean = $index === 0 ? '' : " {$condition['boolean']} ";
            $clauses[] = $boolean . "{$condition['column']} {$condition['operator']} ?";
        }

        return $sql . implode('', $clauses);
    }

    private function buildOrderBy(): string
    {
        if (empty($this->orderByClauses)) {
            return '';
        }

        $parts = [];
        foreach ($this->orderByClauses as $clause) {
            if (array_key_exists('raw', $clause)) {
                $parts[] = $clause['raw'];
            } else {
                $parts[] = trim("{$clause['column']} {$clause['direction']}");
            }
        }

        return ' ORDER BY ' . implode(', ', $parts);
    }

    private function buildWhere(): string
    {
        if (empty($this->wheres)) {
            return '';
        }

        $sql = ' WHERE ';
        $clauses = [];

        foreach ($this->wheres as $index => $where) {
            $boolean = $index === 0 ? '' : " {$where['boolean']} ";

            $clause = match ($where['type']) {
                'basic' => "{$where['column']} {$where['operator']} ?",
                'in' => "{$where['column']} IN (" . implode(', ', array_fill(0, count($where['values']), '?')) . ')',
                'null' => "{$where['column']} IS NULL",
                'not_null' => "{$where['column']} IS NOT NULL",
                'raw' => $where['sql'],
                default => ''
            };

            $clauses[] = $boolean . $clause;
        }

        return $sql . implode('', $clauses);
    }
}
