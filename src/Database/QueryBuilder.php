<?php

declare(strict_types=1);

namespace SedoPHP\Database;

use Generator;
use InvalidArgumentException;
use PDO;
use PDOStatement;

final class QueryBuilder
{
    /** @var list<string> */
    private array $columns = ['*'];

    /** @var list<array<string, mixed>> */
    private array $wheres = [];

    /** @var list<array{table:string,first:string,operator:string,second:string,type:string}> */
    private array $joins = [];

    /** @var list<string> */
    private array $groups = [];

    /** @var list<array<string, mixed>> */
    private array $havings = [];

    /** @var list<array{column:string,direction:string}> */
    private array $orders = [];

    private ?int $limitValue = null;
    private ?int $offsetValue = null;

    public function __construct(private readonly PDO $pdo, private readonly string $table)
    {
        self::assertIdentifier($table);
    }

    public function select(string ...$columns): self
    {
        if ($columns === []) {
            return $this;
        }

        foreach ($columns as $column) {
            self::assertSelectableIdentifier($column);
        }

        $this->columns = $columns;
        return $this;
    }

    public function where(string $column, mixed $operatorOrValue, mixed $value = null): self
    {
        return $this->basicWhere('AND', $column, $operatorOrValue, $value, func_num_args());
    }

    public function orWhere(string $column, mixed $operatorOrValue, mixed $value = null): self
    {
        return $this->basicWhere('OR', $column, $operatorOrValue, $value, func_num_args());
    }

    public function whereNull(string $column): self
    {
        return $this->nullWhere('AND', $column, false);
    }

    public function whereNotNull(string $column): self
    {
        return $this->nullWhere('AND', $column, true);
    }

    /** @param list<mixed> $values */
    public function whereIn(string $column, array $values): self
    {
        return $this->inWhere('AND', $column, $values, false);
    }

    /** @param list<mixed> $values */
    public function whereNotIn(string $column, array $values): self
    {
        return $this->inWhere('AND', $column, $values, true);
    }

    public function whereColumn(string $first, string $operator, string $second): self
    {
        return $this->columnWhere('AND', $first, $operator, $second);
    }

    public function orWhereColumn(string $first, string $operator, string $second): self
    {
        return $this->columnWhere('OR', $first, $operator, $second);
    }

    public function whereExists(QueryBuilder $query): self
    {
        return $this->existsWhere('AND', $query, false);
    }

    public function whereNotExists(QueryBuilder $query): self
    {
        return $this->existsWhere('AND', $query, true);
    }

    public function join(
        string $table,
        string $first,
        string $operator,
        string $second,
        string $type = 'INNER',
    ): self {
        self::assertIdentifier($table);
        self::assertIdentifier($first, true);
        self::assertIdentifier($second, true);

        $operator = strtoupper(trim($operator));
        if (!in_array($operator, ['=', '!=', '<>', '>', '>=', '<', '<='], true)) {
            throw new InvalidArgumentException("Unsupported join operator: {$operator}");
        }

        $type = strtoupper(trim($type));
        if (!in_array($type, ['INNER', 'LEFT', 'RIGHT'], true)) {
            throw new InvalidArgumentException('Join type must be INNER, LEFT or RIGHT.');
        }

        $this->joins[] = compact('table', 'first', 'operator', 'second', 'type');
        return $this;
    }

    public function leftJoin(string $table, string $first, string $operator, string $second): self
    {
        return $this->join($table, $first, $operator, $second, 'LEFT');
    }

    public function rightJoin(string $table, string $first, string $operator, string $second): self
    {
        return $this->join($table, $first, $operator, $second, 'RIGHT');
    }

    public function groupBy(string ...$columns): self
    {
        foreach ($columns as $column) {
            self::assertIdentifier($column, true);
            if (!in_array($column, $this->groups, true)) {
                $this->groups[] = $column;
            }
        }

        return $this;
    }

    public function having(string $column, mixed $operatorOrValue, mixed $value = null): self
    {
        return $this->basicHaving('AND', $column, $operatorOrValue, $value, func_num_args());
    }

    public function orHaving(string $column, mixed $operatorOrValue, mixed $value = null): self
    {
        return $this->basicHaving('OR', $column, $operatorOrValue, $value, func_num_args());
    }

    public function orderBy(string $column, string $direction = 'asc'): self
    {
        self::assertIdentifier($column, true);
        $direction = strtoupper($direction);

        if (!in_array($direction, ['ASC', 'DESC'], true)) {
            throw new InvalidArgumentException('Order direction must be ASC or DESC.');
        }

        $this->orders[] = ['column' => $column, 'direction' => $direction];
        return $this;
    }

    public function limit(int $limit): self
    {
        if ($limit < 1) {
            throw new InvalidArgumentException('Limit must be at least 1.');
        }

        $this->limitValue = $limit;
        return $this;
    }

    public function offset(int $offset): self
    {
        if ($offset < 0) {
            throw new InvalidArgumentException('Offset cannot be negative.');
        }

        $this->offsetValue = $offset;
        return $this;
    }

    /** @return list<array<string, mixed>> */
    public function get(): array
    {
        [$sql, $bindings] = $this->compileSelect();
        return $this->execute($sql, $bindings)->fetchAll();
    }

    public function toSql(): string
    {
        return $this->compileSelect()[0];
    }

    /** @return list<mixed> */
    public function bindings(): array
    {
        return $this->compileSelect()[1];
    }

    /** @return array<string, mixed>|null */
    public function first(): ?array
    {
        $clone = clone $this;
        $clone->limitValue = 1;
        return $clone->get()[0] ?? null;
    }

    /** @return array{data:list<array<string,mixed>>,current_page:int,per_page:int,total:int,last_page:int,from:int|null,to:int|null} */
    public function paginate(int $perPage = 15, int $page = 1): array
    {
        if ($perPage < 1) {
            throw new InvalidArgumentException('Per-page value must be at least 1.');
        }

        $page = max(1, $page);
        $total = $this->count();
        $lastPage = max(1, (int) ceil($total / $perPage));
        $offset = ($page - 1) * $perPage;

        $clone = clone $this;
        $clone->limitValue = $perPage;
        $clone->offsetValue = $offset;
        $data = $clone->get();
        $count = count($data);

        return [
            'data' => $data,
            'current_page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'last_page' => $lastPage,
            'from' => $count === 0 ? null : $offset + 1,
            'to' => $count === 0 ? null : $offset + $count,
        ];
    }

    /**
     * Process results in bounded batches.
     *
     * Returning false from the callback stops iteration early.
     *
     * @param callable(list<array<string,mixed>>, int):mixed $callback
     */
    public function chunk(int $size, callable $callback): int
    {
        if ($size < 1) {
            throw new InvalidArgumentException('Chunk size must be at least 1.');
        }

        $processed = 0;
        $page = 1;
        $baseOffset = $this->offsetValue ?? 0;

        while (true) {
            $query = clone $this;
            $query->limitValue = $size;
            $query->offsetValue = $baseOffset + $processed;
            $rows = $query->get();

            if ($rows === []) {
                break;
            }

            $count = count($rows);
            $processed += $count;

            if ($callback($rows, $page) === false || $count < $size) {
                break;
            }

            $page++;
        }

        return $processed;
    }

    /** @return Generator<int, array<string,mixed>> */
    public function cursor(int $chunkSize = 100): Generator
    {
        if ($chunkSize < 1) {
            throw new InvalidArgumentException('Cursor chunk size must be at least 1.');
        }

        $offset = $this->offsetValue ?? 0;

        while (true) {
            $query = clone $this;
            $query->limitValue = $chunkSize;
            $query->offsetValue = $offset;
            $rows = $query->get();

            if ($rows === []) {
                return;
            }

            foreach ($rows as $row) {
                yield $row;
                $offset++;
            }

            if (count($rows) < $chunkSize) {
                return;
            }
        }
    }

    public function value(string $column): mixed
    {
        self::assertIdentifier($column, true);

        $clone = clone $this;
        $clone->columns = [$column];
        $row = $clone->first();
        $key = self::resultKey($column);

        return $row[$key] ?? null;
    }

    /** @return array<int|string, mixed> */
    public function pluck(string $column, ?string $key = null): array
    {
        self::assertIdentifier($column, true);
        if ($key !== null) {
            self::assertIdentifier($key, true);
        }

        $clone = clone $this;
        $clone->columns = $key === null ? [$column] : [$key, $column];
        $rows = $clone->get();

        $columnKey = self::resultKey($column);
        if ($key === null) {
            return array_values(array_map(
                static fn (array $row): mixed => $row[$columnKey] ?? null,
                $rows
            ));
        }

        $indexKey = self::resultKey($key);
        $result = [];

        foreach ($rows as $row) {
            if (array_key_exists($indexKey, $row)) {
                $result[$row[$indexKey]] = $row[$columnKey] ?? null;
            }
        }

        return $result;
    }

    public function exists(): bool
    {
        return $this->first() !== null;
    }

    public function count(string $column = '*'): int
    {
        if ($column !== '*') {
            self::assertIdentifier($column, true);
        }

        [$whereSql, $whereBindings] = $this->whereSql();
        [$havingSql, $havingBindings] = $this->havingSql();
        $bindings = array_merge($whereBindings, $havingBindings);

        $from = ' FROM ' . $this->quote($this->table)
            . $this->joinSql()
            . $whereSql
            . $this->groupSql()
            . $havingSql;

        if ($this->groups !== [] || $this->havings !== []) {
            $sql = 'SELECT COUNT(*) AS aggregate FROM (SELECT 1' . $from . ') AS sedo_count';
            $row = $this->execute($sql, $bindings)->fetch();
            return (int) ($row['aggregate'] ?? 0);
        }

        $target = $column === '*' ? '*' : $this->quote($column);
        $sql = 'SELECT COUNT(' . $target . ') AS aggregate' . $from;
        $row = $this->execute($sql, $bindings)->fetch();

        return (int) ($row['aggregate'] ?? 0);
    }

    /** @param array<string, mixed> $data */
    public function insert(array $data): int
    {
        if ($data === []) {
            throw new InvalidArgumentException('Insert data cannot be empty.');
        }

        foreach (array_keys($data) as $column) {
            self::assertIdentifier((string) $column, true);
        }

        $columns = array_map(fn (string $column): string => $this->quote($column), array_keys($data));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));

        $sql = 'INSERT INTO ' . $this->quote($this->table)
            . ' (' . implode(', ', $columns) . ') VALUES (' . $placeholders . ')';

        $this->execute($sql, array_values($data));
        return (int) $this->pdo->lastInsertId();
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param string|list<string> $uniqueBy
     * @param list<string>|null $updateColumns
     */
    public function upsert(array $rows, string|array $uniqueBy, ?array $updateColumns = null): int
    {
        if ($rows === []) {
            return 0;
        }

        $rows = array_values($rows);
        $columns = array_keys($rows[0]);
        if ($columns === []) {
            throw new InvalidArgumentException('Upsert rows cannot be empty.');
        }

        foreach ($columns as $column) {
            self::assertIdentifier((string) $column, true);
        }

        foreach ($rows as $row) {
            if (array_keys($row) !== $columns) {
                throw new InvalidArgumentException('All upsert rows must contain the same columns in the same order.');
            }
        }

        $uniqueColumns = is_array($uniqueBy) ? array_values($uniqueBy) : [$uniqueBy];
        if ($uniqueColumns === []) {
            throw new InvalidArgumentException('Upsert requires at least one unique column.');
        }

        foreach ($uniqueColumns as $column) {
            self::assertIdentifier($column, true);
            if (!in_array($column, $columns, true)) {
                throw new InvalidArgumentException("Upsert unique column is missing from rows: {$column}");
            }
        }

        $updateColumns ??= array_values(array_diff($columns, $uniqueColumns));
        foreach ($updateColumns as $column) {
            self::assertIdentifier($column, true);
            if (!in_array($column, $columns, true)) {
                throw new InvalidArgumentException("Upsert update column is missing from rows: {$column}");
            }
        }

        $quotedColumns = implode(', ', array_map(fn (string $column): string => $this->quote($column), $columns));
        $rowPlaceholder = '(' . implode(', ', array_fill(0, count($columns), '?')) . ')';
        $sql = 'INSERT INTO ' . $this->quote($this->table)
            . ' (' . $quotedColumns . ') VALUES '
            . implode(', ', array_fill(0, count($rows), $rowPlaceholder));

        $driver = Database::driver();
        if ($driver === 'sqlite') {
            $conflict = implode(', ', array_map(fn (string $column): string => $this->quote($column), $uniqueColumns));
            if ($updateColumns === []) {
                $sql .= ' ON CONFLICT (' . $conflict . ') DO NOTHING';
            } else {
                $sets = array_map(
                    fn (string $column): string => $this->quote($column) . ' = excluded.' . $this->quote($column),
                    $updateColumns
                );
                $sql .= ' ON CONFLICT (' . $conflict . ') DO UPDATE SET ' . implode(', ', $sets);
            }
        } elseif ($driver === 'mysql') {
            if ($updateColumns === []) {
                $column = $uniqueColumns[0];
                $sql .= ' ON DUPLICATE KEY UPDATE ' . $this->quote($column) . ' = ' . $this->quote($column);
            } else {
                $sets = array_map(
                    fn (string $column): string => $this->quote($column) . ' = VALUES(' . $this->quote($column) . ')',
                    $updateColumns
                );
                $sql .= ' ON DUPLICATE KEY UPDATE ' . implode(', ', $sets);
            }
        } else {
            throw new InvalidArgumentException("Upsert is not supported by database driver: {$driver}");
        }

        $bindings = [];
        foreach ($rows as $row) {
            array_push($bindings, ...array_values($row));
        }

        return $this->execute($sql, $bindings)->rowCount();
    }

    /** @param array<string,mixed> $attributes @param array<string,mixed> $values */
    public function updateOrInsert(array $attributes, array $values = []): bool
    {
        if ($attributes === []) {
            throw new InvalidArgumentException('updateOrInsert attributes cannot be empty.');
        }

        $query = clone $this;
        foreach ($attributes as $column => $value) {
            $query->where((string) $column, $value);
        }

        if ($query->exists()) {
            return $values === [] || $query->update($values) >= 0;
        }

        $this->insert(array_merge($attributes, $values));
        return true;
    }

    /** @param array<string,mixed> $attributes @param array<string,mixed> $values @return array<string,mixed> */
    public function firstOrCreate(array $attributes, array $values = []): array
    {
        if ($attributes === []) {
            throw new InvalidArgumentException('firstOrCreate attributes cannot be empty.');
        }

        $query = clone $this;
        foreach ($attributes as $column => $value) {
            $query->where((string) $column, $value);
        }

        $existing = $query->first();
        if ($existing !== null) {
            return $existing;
        }

        $data = array_merge($attributes, $values);
        $id = $this->insert($data);

        $created = clone $this;
        foreach ($attributes as $column => $value) {
            $created->where((string) $column, $value);
        }

        return $created->first() ?? ($id > 0 ? ['id' => $id] + $data : $data);
    }

    /** @param array<string,mixed> $attributes @param array<string,mixed> $values @return array<string,mixed> */
    public function firstOrNew(array $attributes, array $values = []): array
    {
        if ($attributes === []) {
            throw new InvalidArgumentException('firstOrNew attributes cannot be empty.');
        }

        $query = clone $this;
        foreach ($attributes as $column => $value) {
            $query->where((string) $column, $value);
        }

        return $query->first() ?? array_merge($attributes, $values);
    }

    /** @param array<string, mixed> $data */
    public function update(array $data): int
    {
        if ($data === []) {
            return 0;
        }
        if ($this->wheres === []) {
            throw new InvalidArgumentException('Refusing to update every row without a where clause.');
        }

        $this->assertSimpleMutation();

        $sets = [];
        $bindings = [];

        foreach ($data as $column => $value) {
            self::assertIdentifier((string) $column, true);
            $sets[] = $this->quote((string) $column) . ' = ?';
            $bindings[] = $value;
        }

        [$whereSql, $whereBindings] = $this->whereSql();
        $sql = 'UPDATE ' . $this->quote($this->table)
            . ' SET ' . implode(', ', $sets)
            . $whereSql;

        return $this->execute($sql, array_merge($bindings, $whereBindings))->rowCount();
    }

    public function delete(): int
    {
        if ($this->wheres === []) {
            throw new InvalidArgumentException('Refusing to delete every row without a where clause.');
        }

        $this->assertSimpleMutation();

        [$whereSql, $bindings] = $this->whereSql();
        $sql = 'DELETE FROM ' . $this->quote($this->table) . $whereSql;

        return $this->execute($sql, $bindings)->rowCount();
    }

    private function basicWhere(string $boolean, string $column, mixed $operatorOrValue, mixed $value, int $argc): self
    {
        self::assertIdentifier($column, true);
        $operator = $argc === 2 ? '=' : strtoupper((string) $operatorOrValue);
        $actualValue = $argc === 2 ? $operatorOrValue : $value;

        if (!in_array($operator, ['=', '!=', '<>', '>', '>=', '<', '<=', 'LIKE'], true)) {
            throw new InvalidArgumentException("Unsupported where operator: {$operator}");
        }

        if ($actualValue === null) {
            return $this->nullWhere($boolean, $column, in_array($operator, ['!=', '<>'], true));
        }

        $this->wheres[] = [
            'type' => 'basic',
            'column' => $column,
            'operator' => $operator,
            'value' => $actualValue,
            'boolean' => $boolean,
        ];

        return $this;
    }

    private function columnWhere(string $boolean, string $first, string $operator, string $second): self
    {
        self::assertIdentifier($first, true);
        self::assertIdentifier($second, true);

        $operator = strtoupper(trim($operator));
        if (!in_array($operator, ['=', '!=', '<>', '>', '>=', '<', '<='], true)) {
            throw new InvalidArgumentException("Unsupported column comparison operator: {$operator}");
        }

        $this->wheres[] = [
            'type' => 'column',
            'first' => $first,
            'operator' => $operator,
            'second' => $second,
            'boolean' => $boolean,
        ];

        return $this;
    }

    private function existsWhere(string $boolean, QueryBuilder $query, bool $not): self
    {
        if ($query === $this) {
            throw new InvalidArgumentException('A query cannot contain itself as an EXISTS subquery.');
        }

        $this->wheres[] = [
            'type' => 'exists',
            'query' => clone $query,
            'not' => $not,
            'boolean' => $boolean,
        ];

        return $this;
    }

    private function nullWhere(string $boolean, string $column, bool $not): self
    {
        self::assertIdentifier($column, true);
        $this->wheres[] = [
            'type' => 'null',
            'column' => $column,
            'not' => $not,
            'boolean' => $boolean,
        ];

        return $this;
    }

    /** @param list<mixed> $values */
    private function inWhere(string $boolean, string $column, array $values, bool $not): self
    {
        self::assertIdentifier($column, true);
        $this->wheres[] = [
            'type' => 'in',
            'column' => $column,
            'values' => array_values($values),
            'not' => $not,
            'boolean' => $boolean,
        ];

        return $this;
    }

    private function basicHaving(string $boolean, string $column, mixed $operatorOrValue, mixed $value, int $argc): self
    {
        self::assertIdentifier($column, true);
        $operator = $argc === 2 ? '=' : strtoupper((string) $operatorOrValue);
        $actualValue = $argc === 2 ? $operatorOrValue : $value;

        if (!in_array($operator, ['=', '!=', '<>', '>', '>=', '<', '<=', 'LIKE'], true)) {
            throw new InvalidArgumentException("Unsupported having operator: {$operator}");
        }

        $this->havings[] = [
            'column' => $column,
            'operator' => $operator,
            'value' => $actualValue,
            'boolean' => $boolean,
        ];

        return $this;
    }

    /** @return array{0:string,1:list<mixed>} */
    private function whereSql(): array
    {
        if ($this->wheres === []) {
            return ['', []];
        }

        $parts = [];
        $bindings = [];

        foreach ($this->wheres as $index => $where) {
            $prefix = $index === 0 ? '' : ' ' . $where['boolean'] . ' ';

            if ($where['type'] === 'null') {
                $parts[] = $prefix . $this->quote((string) $where['column'])
                    . ((bool) $where['not'] ? ' IS NOT NULL' : ' IS NULL');
                continue;
            }

            if ($where['type'] === 'in') {
                $values = (array) $where['values'];
                if ($values === []) {
                    $parts[] = $prefix . ((bool) $where['not'] ? '1 = 1' : '1 = 0');
                    continue;
                }

                $parts[] = $prefix . $this->quote((string) $where['column'])
                    . ((bool) $where['not'] ? ' NOT IN (' : ' IN (')
                    . implode(', ', array_fill(0, count($values), '?')) . ')';

                array_push($bindings, ...$values);
                continue;
            }

            if ($where['type'] === 'column') {
                $parts[] = $prefix . $this->quote((string) $where['first'])
                    . ' ' . $where['operator'] . ' '
                    . $this->quote((string) $where['second']);
                continue;
            }

            if ($where['type'] === 'exists') {
                /** @var QueryBuilder $subquery */
                $subquery = $where['query'];
                [$subquerySql, $subqueryBindings] = $subquery->compileSelect();
                $parts[] = $prefix . ((bool) $where['not'] ? 'NOT EXISTS (' : 'EXISTS (') . $subquerySql . ')';
                array_push($bindings, ...$subqueryBindings);
                continue;
            }

            $parts[] = $prefix . $this->quote((string) $where['column'])
                . ' ' . $where['operator'] . ' ?';
            $bindings[] = $where['value'];
        }

        return [' WHERE ' . implode('', $parts), $bindings];
    }

    /** @return array{0:string,1:list<mixed>} */
    private function havingSql(): array
    {
        if ($this->havings === []) {
            return ['', []];
        }

        $parts = [];
        $bindings = [];

        foreach ($this->havings as $index => $having) {
            $prefix = $index === 0 ? '' : ' ' . $having['boolean'] . ' ';
            $value = $having['value'];

            if ($value === null) {
                $not = in_array($having['operator'], ['!=', '<>'], true);
                $parts[] = $prefix . $this->quote((string) $having['column'])
                    . ($not ? ' IS NOT NULL' : ' IS NULL');
                continue;
            }

            $parts[] = $prefix . $this->quote((string) $having['column'])
                . ' ' . $having['operator'] . ' ?';
            $bindings[] = $value;
        }

        return [' HAVING ' . implode('', $parts), $bindings];
    }

    private function joinSql(): string
    {
        $sql = '';

        foreach ($this->joins as $join) {
            $sql .= ' ' . $join['type'] . ' JOIN ' . $this->quote($join['table'])
                . ' ON ' . $this->quote($join['first'])
                . ' ' . $join['operator']
                . ' ' . $this->quote($join['second']);
        }

        return $sql;
    }

    private function groupSql(): string
    {
        if ($this->groups === []) {
            return '';
        }

        return ' GROUP BY ' . implode(', ', array_map(
            fn (string $column): string => $this->quote($column),
            $this->groups
        ));
    }

    private function orderSql(): string
    {
        if ($this->orders === []) {
            return '';
        }

        $parts = array_map(
            fn (array $order): string => $this->quote($order['column']) . ' ' . $order['direction'],
            $this->orders
        );

        return ' ORDER BY ' . implode(', ', $parts);
    }

    private function limitSql(): string
    {
        $sql = '';

        if ($this->limitValue !== null) {
            $sql .= ' LIMIT ' . $this->limitValue;
        }

        if ($this->offsetValue !== null) {
            if ($this->limitValue === null) {
                $sql .= Database::driver() === 'sqlite'
                    ? ' LIMIT -1'
                    : ' LIMIT 18446744073709551615';
            }

            $sql .= ' OFFSET ' . $this->offsetValue;
        }

        return $sql;
    }

    private function assertSimpleMutation(): void
    {
        if ($this->joins !== [] || $this->groups !== [] || $this->havings !== []) {
            throw new InvalidArgumentException('Joined or grouped update/delete operations are not supported.');
        }
    }

    /** @return array{0:string,1:list<mixed>} */
    private function compileSelect(): array
    {
        [$whereSql, $whereBindings] = $this->whereSql();
        [$havingSql, $havingBindings] = $this->havingSql();

        $columns = implode(', ', array_map(
            fn (string $column): string => $this->quoteSelectable($column),
            $this->columns
        ));

        $sql = 'SELECT ' . $columns
            . ' FROM ' . $this->quote($this->table)
            . $this->joinSql()
            . $whereSql
            . $this->groupSql()
            . $havingSql
            . $this->orderSql()
            . $this->limitSql();

        return [$sql, array_merge($whereBindings, $havingBindings)];
    }

    /** @param list<mixed> $bindings */
    private function execute(string $sql, array $bindings): PDOStatement
    {
        $startedAt = Database::diagnosticsEnabled() ? hrtime(true) : null;

        try {
            $statement = $this->pdo->prepare($sql);
            $statement->execute($bindings);
            return $statement;
        } finally {
            if ($startedAt !== null) {
                Database::recordQuery(
                    $sql,
                    count($bindings),
                    (hrtime(true) - $startedAt) / 1_000_000
                );
            }
        }
    }

    private function quoteSelectable(string $identifier): string
    {
        if ($identifier === '*') {
            return '*';
        }

        if (str_ends_with($identifier, '.*')) {
            return $this->quote(substr($identifier, 0, -2)) . '.*';
        }

        return $this->quote($identifier);
    }

    private function quote(string $identifier): string
    {
        $quote = Database::driver() === 'mysql' ? chr(96) : '"';

        return implode('.', array_map(
            static fn (string $part): string => $quote . $part . $quote,
            explode('.', $identifier)
        ));
    }

    private static function resultKey(string $identifier): string
    {
        $parts = explode('.', $identifier);
        return (string) end($parts);
    }

    private static function assertSelectableIdentifier(string $identifier): void
    {
        if ($identifier === '*') {
            return;
        }

        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*(?:\.(?:[A-Za-z_][A-Za-z0-9_]*|\*))?$/', $identifier) !== 1) {
            throw new InvalidArgumentException("Invalid SQL identifier: {$identifier}");
        }
    }

    private static function assertIdentifier(string $identifier, bool $allowDot = false): void
    {
        $pattern = $allowDot
            ? '/^[A-Za-z_][A-Za-z0-9_]*(?:\.[A-Za-z_][A-Za-z0-9_]*)?$/'
            : '/^[A-Za-z_][A-Za-z0-9_]*$/';

        if (preg_match($pattern, $identifier) !== 1) {
            throw new InvalidArgumentException("Invalid SQL identifier: {$identifier}");
        }
    }
}
