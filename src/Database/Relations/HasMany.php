<?php

declare(strict_types=1);

namespace SedoPHP\Database\Relations;

use SedoPHP\Database\Model;
use SedoPHP\Database\QueryBuilder;

/** @template T of Model */
final class HasMany
{
    /** @param class-string<T> $related */
    public function __construct(
        private readonly string $related,
        private readonly string $foreignKey,
        private readonly mixed $localValue,
    ) {
    }

    public function query(): QueryBuilder
    {
        $query = ($this->related)::query();

        return $this->localValue === null
            ? $query->whereIn($this->foreignKey, [])
            : $query->where($this->foreignKey, $this->localValue);
    }

    /** @return list<T> */
    public function get(): array
    {
        $class = $this->related;
        return array_map(
            static fn (array $row) => new $class($row),
            $this->query()->get()
        );
    }

    /** @return T|null */
    public function first(): ?Model
    {
        $row = $this->query()->first();
        if ($row === null) {
            return null;
        }

        $class = $this->related;
        return new $class($row);
    }

    public function count(): int
    {
        return $this->query()->count();
    }

    /** @return class-string<T> */
    public function relatedClass(): string { return $this->related; }
    public function foreignKey(): string { return $this->foreignKey; }
    public function localValue(): mixed { return $this->localValue; }
}
