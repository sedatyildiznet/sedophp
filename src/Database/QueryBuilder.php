<?php

declare(strict_types=1);

namespace SedoPHP\Database;

use InvalidArgumentException;
use PDO;
use PDOStatement;

final class QueryBuilder
{
    /** @var list<string> */
    private array $columns = ['*'];
    /** @var list<array<string, mixed>> */
    private array $wheres = [];
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
            if ($column !== '*') {
                self::assertIdentifier($column, true);
            }
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
        [$whereSql, $bindings] = $this->whereSql();
        $columns = implode(', ', array_map(
            fn (string $column) => $column === '*' ? '*' : $this->quote($column),
            $this->columns
        ));
        $sql = 'SELECT ' . $columns . ' FROM ' . $this->quote($this->table)
            . $whereSql . $this->orderSql() . $this->limitSql();

        return $this->execute($sql, $bindings)->fetchAll();
    }

    /** @return array<string, mixed>|null */
    public function first(): ?array
    {
        $clone = clone $this;
        $clone->limitValue = 1;
        return $clone->get()[0] ?? null;
    }

    public function value(string $column): mixed
    {
        self::assertIdentifier($column, true);
        $clone = clone $this;
        $clone->columns = [$column];
        $row = $clone->first();
        return $row[$column] ?? null;
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

        if ($key === null) {
            return array_values(array_map(static fn (array $row) => $row[$column] ?? null, $rows));
        }

        $result = [];
        foreach ($rows as $row) {
            if (array_key_exists($key, $row)) {
                $result[$row[$key]] = $row[$column] ?? null;
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
        [$whereSql, $bindings] = $this->whereSql();
        $target = $column === '*' ? '*' : $this->quote($column);
        $sql = 'SELECT COUNT(' . $target . ') AS aggregate FROM ' . $this->quote($this->table) . $whereSql;
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

        $columns = array_map(fn (string $column) => $this->quote($column), array_keys($data));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));
        $sql = 'INSERT INTO ' . $this->quote($this->table)
            . ' (' . implode(', ', $columns) . ') VALUES (' . $placeholders . ')';

        $this->execute($sql, array_values($data));
        return (int) $this->pdo->lastInsertId();
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

        $sets = [];
        $bindings = [];
        foreach ($data as $column => $value) {
            self::assertIdentifier((string) $column, true);
            $sets[] = $this->quote((string) $column) . ' = ?';
            $bindings[] = $value;
        }

        [$whereSql, $whereBindings] = $this->whereSql();
        $sql = 'UPDATE ' . $this->quote($this->table) . ' SET ' . implode(', ', $sets) . $whereSql;
        return $this->execute($sql, array_merge($bindings, $whereBindings))->rowCount();
    }

    public function delete(): int
    {
        if ($this->wheres === []) {
            throw new InvalidArgumentException('Refusing to delete every row without a where clause.');
        }
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

            $parts[] = $prefix . $this->quote((string) $where['column'])
                . ' ' . $where['operator'] . ' ?';
            $bindings[] = $where['value'];
        }

        return [' WHERE ' . implode('', $parts), $bindings];
    }

    private function orderSql(): string
    {
        if ($this->orders === []) {
            return '';
        }
        $parts = array_map(
            fn (array $order) => $this->quote($order['column']) . ' ' . $order['direction'],
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

    /** @param list<mixed> $bindings */
    private function execute(string $sql, array $bindings): PDOStatement
    {
        $statement = $this->pdo->prepare($sql);
        $statement->execute($bindings);
        return $statement;
    }

    private function quote(string $identifier): string
    {
        $quote = Database::driver() === 'mysql' ? chr(96) : '"';
        return implode('.', array_map(
            static fn (string $part) => $quote . $part . $quote,
            explode('.', $identifier)
        ));
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
