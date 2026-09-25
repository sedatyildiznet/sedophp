<?php

declare(strict_types=1);

namespace SedoPHP\Database\Relations;

use InvalidArgumentException;
use SedoPHP\Database\Database;
use SedoPHP\Database\Model;
use SedoPHP\Database\QueryBuilder;

/** @template T of Model */
final class BelongsToMany
{
    /** @var list<string> */
    private array $pivotColumns = [];

    /** @param class-string<T> $related */
    public function __construct(
        private readonly string $related,
        private readonly string $pivotTable,
        private readonly string $foreignPivotKey,
        private readonly string $relatedPivotKey,
        private readonly mixed $parentValue,
        private readonly string $relatedKey = 'id',
    ) {
        self::assertIdentifier($pivotTable);
        self::assertIdentifier($foreignPivotKey);
        self::assertIdentifier($relatedPivotKey);
        self::assertIdentifier($relatedKey);
    }

    public function withPivot(string ...$columns): self
    {
        foreach ($columns as $column) {
            self::assertIdentifier($column);
            if (!in_array($column, $this->pivotColumns, true)) {
                $this->pivotColumns[] = $column;
            }
        }

        return $this;
    }

    public function query(): QueryBuilder
    {
        $query = ($this->related)::query();

        if ($this->parentValue === null) {
            return $query->whereIn($this->relatedKey, []);
        }

        $ids = Database::table($this->pivotTable)
            ->where($this->foreignPivotKey, $this->parentValue)
            ->pluck($this->relatedPivotKey);

        return $query->whereIn($this->relatedKey, array_values($ids));
    }

    /** @return list<T> */
    public function get(): array
    {
        if ($this->parentValue === null) {
            return [];
        }

        $pivotRows = Database::table($this->pivotTable)
            ->where($this->foreignPivotKey, $this->parentValue)
            ->get();

        if ($pivotRows === []) {
            return [];
        }

        $ids = array_values(array_unique(array_filter(
            array_map(
                fn (array $row): mixed => $row[$this->relatedPivotKey] ?? null,
                $pivotRows
            ),
            static fn (mixed $value): bool => $value !== null
        ), SORT_REGULAR));

        if ($ids === []) {
            return [];
        }

        $class = $this->related;
        $rows = $class::query()->whereIn($this->relatedKey, $ids)->get();
        $indexed = [];

        foreach ($rows as $row) {
            $indexed[(string) ($row[$this->relatedKey] ?? '')] = $row;
        }

        $models = [];
        foreach ($pivotRows as $pivotRow) {
            $key = (string) ($pivotRow[$this->relatedPivotKey] ?? '');
            if (!isset($indexed[$key])) {
                continue;
            }

            $model = new $class($indexed[$key]);
            $model->setRelation('pivot', $this->pivotData($pivotRow));
            $models[] = $model;
        }

        return $models;
    }

    public function count(): int
    {
        return $this->query()->count();
    }

    /** @param array<string, mixed> $row @return array<string, mixed> */
    public function pivotData(array $row): array
    {
        $columns = array_values(array_unique(array_merge(
            [$this->foreignPivotKey, $this->relatedPivotKey],
            $this->pivotColumns
        )));

        return array_intersect_key($row, array_flip($columns));
    }

    /** @return class-string<T> */
    public function relatedClass(): string { return $this->related; }
    public function pivotTable(): string { return $this->pivotTable; }
    public function foreignPivotKey(): string { return $this->foreignPivotKey; }
    public function relatedPivotKey(): string { return $this->relatedPivotKey; }
    public function parentValue(): mixed { return $this->parentValue; }
    public function relatedKey(): string { return $this->relatedKey; }

    private static function assertIdentifier(string $identifier): void
    {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $identifier) !== 1) {
            throw new InvalidArgumentException("Invalid relation identifier: {$identifier}");
        }
    }
}
