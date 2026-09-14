<?php

declare(strict_types=1);

namespace SedoPHP\Database;

use ArrayAccess;
use JsonSerializable;
use RuntimeException;

/** @implements ArrayAccess<string, mixed> */
abstract class Model implements ArrayAccess, JsonSerializable
{
    protected string $table = '';
    protected string $primaryKey = 'id';
    /** @var list<string> */
    protected array $fillable = [];
    /** @var list<string> */
    protected array $hidden = ['password'];
    /** @var array<string, mixed> */
    private array $attributes = [];

    /** @param array<string, mixed> $attributes */
    public function __construct(array $attributes = [])
    {
        $this->attributes = $attributes;
    }

    public static function query(): QueryBuilder
    {
        $model = new static();
        return Database::table($model->tableName());
    }

    public static function find(int|string $id): ?static
    {
        $model = new static();
        $row = Database::table($model->tableName())->where($model->primaryKey, $id)->first();
        return $row === null ? null : new static($row);
    }

    /** @return list<static> */
    public static function all(): array
    {
        return array_map(static fn (array $row) => new static($row), static::query()->get());
    }

    /** @param array<string, mixed> $data */
    public static function create(array $data): static
    {
        $model = new static();
        $data = $model->filterFillable($data);
        $id = Database::table($model->tableName())->insert($data);
        if ($id > 0 && !array_key_exists($model->primaryKey, $data)) {
            $data[$model->primaryKey] = $id;
        }
        return new static($data);
    }

    /** @param array<string, mixed> $data */
    public function update(array $data): bool
    {
        $id = $this->attributes[$this->primaryKey] ?? null;
        if ($id === null) {
            throw new RuntimeException('Cannot update a model without a primary key.');
        }
        $data = $this->filterFillable($data);
        if ($data === []) {
            return false;
        }
        Database::table($this->tableName())->where($this->primaryKey, $id)->update($data);
        $this->attributes = array_merge($this->attributes, $data);
        return true;
    }

    public function delete(): bool
    {
        $id = $this->attributes[$this->primaryKey] ?? null;
        if ($id === null) {
            throw new RuntimeException('Cannot delete a model without a primary key.');
        }
        return Database::table($this->tableName())->where($this->primaryKey, $id)->delete() > 0;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->attributes[$key] ?? $default;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return array_diff_key($this->attributes, array_flip($this->hidden));
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    public function offsetExists(mixed $offset): bool
    {
        return is_string($offset) && array_key_exists($offset, $this->attributes);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return is_string($offset) ? ($this->attributes[$offset] ?? null) : null;
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        if (!is_string($offset)) {
            throw new RuntimeException('Model keys must be strings.');
        }
        $this->attributes[$offset] = $value;
    }

    public function offsetUnset(mixed $offset): void
    {
        if (is_string($offset)) {
            unset($this->attributes[$offset]);
        }
    }

    private function tableName(): string
    {
        if ($this->table === '') {
            throw new RuntimeException(static::class . ' must define a table name.');
        }
        return $this->table;
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    private function filterFillable(array $data): array
    {
        if ($this->fillable === []) {
            return $data;
        }
        return array_intersect_key($data, array_flip($this->fillable));
    }
}
