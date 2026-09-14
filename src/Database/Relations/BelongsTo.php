<?php

declare(strict_types=1);

namespace SedoPHP\Database\Relations;

use SedoPHP\Database\Model;
use SedoPHP\Database\QueryBuilder;

/** @template T of Model */
final class BelongsTo
{
    /** @param class-string<T> $related */
    public function __construct(
        private readonly string $related,
        private readonly string $ownerKey,
        private readonly mixed $foreignValue,
    ) {
    }

    public function query(): QueryBuilder
    {
        $query = ($this->related)::query();

        return $this->foreignValue === null
            ? $query->whereIn($this->ownerKey, [])
            : $query->where($this->ownerKey, $this->foreignValue);
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

    /** @return class-string<T> */
    public function relatedClass(): string { return $this->related; }
    public function ownerKey(): string { return $this->ownerKey; }
    public function foreignValue(): mixed { return $this->foreignValue; }
}
