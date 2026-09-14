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
    /** @var list<array{column:string,operator:string,value:mixed,boolean:string}> */
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
        self::assertIdentifier($column, true);
        $argc = func_num_args();
        $operator = $argc === 2 ? '=' : strtoupper((string) $operatorOrValue);
        $actualValue = $argc === 2 ? $operatorOrValue : $value;

        if (!in_array($operator, ['=', '!=', '<>', '>', '>=', '<', '<=', 'LIKE'], true)) {
            throw new InvalidArgumentException("Unsupported where operator: {$operator}");
        }

        $this->wheres[] = ['column' => $column, 'operator' => $operator, 'value' => $actualValue, 'boolean' => 'AND'];
        return $this;
    }

    public function orWhere(string $column, mixed $operatorOrValue, mixed $value = null): self
    {
        self::assertIdentifier($column, true);
        $argc = func_num_args();
        $operator = $argc === 2 ? '=' : strtoupper((string) $operatorOrValue);
        $actualValue = $argc === 2 ? $operatorOrValue : $value;

        if (!in_array($operator, ['=', '!=', '<>', '>', '>=', '<', '<=', 'LIKE'], true)) {
            throw new InvalidArgumentException("Unsupported where operator: {$operator}");
        }

        $this->wheres[] = ['column' => $column, 'operator' => $operator, 'value' => $actualValue, 'boolean' => 'OR'];
        return $this;
    }

    public function whereNull(string $column): self
    {
        self::assertIdentifier($column, true);
        $this->wheres[] = ['column' => $column, 'operator' => 'IS NULL', 'value' => null, 'boolean' => 'AND'];
        return $this;
    }

    public function whereNotNull(string $column): self
    {
        self::assertIdentifier($column, true);
        $this->wheres[] = ['column' => $column, 'operator' => 'IS NOT NULL', 'value' => null, 'boolean' => 'AND'];
        return $this;
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
        $columns = implode(', ', array_map(fn (string $column) => $column === '*' ? '*' : $this->quote($column), $this->columns));
        $sql = 'SELECT ' . $columns . ' FROM ' . $this->quote($this->table) . $whereSql . $this->orderSql() . $this->limitSql();
        $statement = $this->execute($sql, $bindings);
        return $statement->fetchAll();
    }

    /** @return array<string, mixed>|null */
    public function first(): ?array
    {
        $clone = clone $this;
        $clone->limitValue = 1;
        $rows = $clone->get();
        return $rows[0] ?? null;
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
        $sql = 'INSERT INTO ' . $this->quote($this->table) . ' (' . implode(', ', $columns) . ') VALUES (' . $placeholders . ')';
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
            if (in_array($where['operator'], ['IS NULL', 'IS NOT NULL'], true)) {
                $parts[] = $prefix . $this->quote($where['column']) . ' ' . $where['operator'];
                continue;
            }
            $parts[] = $prefix . $this->quote($where['column']) . ' ' . $where['operator'] . ' ?';
            $bindings[] = $where['value'];
        }
        return [' WHERE ' . implode('', $parts), $bindings];
    }

    private function orderSql(): string
    {
        if ($this->orders === []) {
            return '';
        }
        $parts = array_map(fn (array $order) => $this->quote($order['column']) . ' ' . $order['direction'], $this->orders);
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
                $sql .= Database::driver() === 'sqlite' ? ' LIMIT -1' : ' LIMIT 18446744073709551615';
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
        $quote = Database::driver() === 'mysql' ? '`' : '"';
        return implode('.', array_map(static fn (string $part) => $quote . $part . $quote, explode('.', $identifier)));
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
