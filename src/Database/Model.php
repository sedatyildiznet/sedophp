<?php

declare(strict_types=1);

namespace SedoPHP\Database;

use ArrayAccess;
use DateTimeImmutable;
use DateTimeInterface;
use JsonException;
use JsonSerializable;
use RuntimeException;
use SedoPHP\Database\Relations\BelongsTo;
use SedoPHP\Database\Relations\BelongsToMany;
use SedoPHP\Database\Relations\HasMany;
use SedoPHP\Database\Relations\HasOne;

/** @implements ArrayAccess<string, mixed> */
abstract class Model implements ArrayAccess, JsonSerializable
{
    protected string $table = '';
    protected string $primaryKey = 'id';

    /** @var list<string> */
    protected array $fillable = [];

    /** @var list<string> */
    protected array $hidden = ['password'];

    /** @var array<string, string> */
    protected array $casts = [];

    protected bool $timestamps = false;
    protected string $createdAt = 'created_at';
    protected string $updatedAt = 'updated_at';

    protected bool $softDeletes = false;
    protected string $deletedAt = 'deleted_at';

    /** @var array<string, mixed> */
    private array $attributes = [];

    /** @param array<string, mixed> $attributes */
    public function __construct(array $attributes = [])
    {
        $this->attributes = $this->castAttributes($attributes);
    }

    public static function query(): QueryBuilder
    {
        $model = new static();
        $query = Database::table($model->tableName());

        return $model->softDeletes
            ? $query->whereNull($model->deletedAt)
            : $query;
    }

    public static function withTrashed(): QueryBuilder
    {
        $model = new static();
        return Database::table($model->tableName());
    }

    public static function onlyTrashed(): QueryBuilder
    {
        $model = new static();

        if (!$model->softDeletes) {
            throw new RuntimeException(static::class . ' does not enable soft deletes.');
        }

        return Database::table($model->tableName())->whereNotNull($model->deletedAt);
    }

    public static function find(int|string $id): ?static
    {
        $model = new static();
        $row = static::query()->where($model->primaryKey, $id)->first();
        return $row === null ? null : new static($row);
    }

    /** @return list<static> */
    public static function all(): array
    {
        return array_map(static fn (array $row) => new static($row), static::query()->get());
    }

    /**
     * @param string|list<string>|array<string, callable(QueryBuilder):mixed> $relations
     * @return list<static>
     */
    public static function with(string|array $relations): array
    {
        $models = static::all();

        if (is_string($relations)) {
            static::eagerLoad($models, $relations);
            return $models;
        }

        foreach ($relations as $key => $value) {
            if (is_int($key)) {
                static::eagerLoad($models, (string) $value);
                continue;
            }

            if (!is_callable($value)) {
                throw new RuntimeException('Eager-load constraint for ' . $key . ' must be callable.');
            }

            static::eagerLoad($models, (string) $key, $value);
        }

        return $models;
    }

    /** @param array<string, mixed> $data */
    public static function create(array $data): static
    {
        $model = new static();
        $data = $model->filterFillable($data);
        $data = $model->applyCreateTimestamps($data);

        $id = Database::table($model->tableName())->insert($model->serializeForDatabase($data));

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
        $data = $this->applyUpdateTimestamp($data);

        if ($data === []) {
            return false;
        }

        $query = Database::table($this->tableName())->where($this->primaryKey, $id);
        if ($this->softDeletes) {
            $query->whereNull($this->deletedAt);
        }

        $updated = $query->update($this->serializeForDatabase($data));
        if ($updated < 1) {
            return false;
        }

        $this->attributes = array_merge($this->attributes, $this->castAttributes($data));
        return true;
    }

    public function delete(): bool
    {
        $id = $this->attributes[$this->primaryKey] ?? null;
        if ($id === null) {
            throw new RuntimeException('Cannot delete a model without a primary key.');
        }

        if (!$this->softDeletes) {
            return Database::table($this->tableName())->where($this->primaryKey, $id)->delete() > 0;
        }

        if ($this->trashed()) {
            return false;
        }

        $deletedAt = gmdate('Y-m-d H:i:s');
        $updated = Database::table($this->tableName())
            ->where($this->primaryKey, $id)
            ->whereNull($this->deletedAt)
            ->update([$this->deletedAt => $deletedAt]);

        if ($updated > 0) {
            $this->attributes[$this->deletedAt] = $deletedAt;
            return true;
        }

        return false;
    }

    public function restore(): bool
    {
        if (!$this->softDeletes) {
            throw new RuntimeException(static::class . ' does not enable soft deletes.');
        }

        $id = $this->attributes[$this->primaryKey] ?? null;
        if ($id === null) {
            throw new RuntimeException('Cannot restore a model without a primary key.');
        }

        if (!$this->trashed()) {
            return false;
        }

        $updated = Database::table($this->tableName())
            ->where($this->primaryKey, $id)
            ->whereNotNull($this->deletedAt)
            ->update([$this->deletedAt => null]);

        if ($updated > 0) {
            $this->attributes[$this->deletedAt] = null;
            return true;
        }

        return false;
    }

    public function forceDelete(): bool
    {
        $id = $this->attributes[$this->primaryKey] ?? null;
        if ($id === null) {
            throw new RuntimeException('Cannot force-delete a model without a primary key.');
        }

        return Database::table($this->tableName())->where($this->primaryKey, $id)->delete() > 0;
    }

    public function trashed(): bool
    {
        return $this->softDeletes && ($this->attributes[$this->deletedAt] ?? null) !== null;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->attributes[$key] ?? $default;
    }

    public function getKey(): mixed
    {
        return $this->attributes[$this->primaryKey] ?? null;
    }

    public function getTable(): string
    {
        return $this->tableName();
    }

    public function getPrimaryKeyName(): string
    {
        return $this->primaryKey;
    }

    public function setRelation(string $name, mixed $value): void
    {
        $this->attributes[$name] = $value;
    }

    /** @param class-string<Model> $related */
    protected function hasMany(string $related, string $foreignKey, ?string $localKey = null): HasMany
    {
        $key = $localKey ?? $this->primaryKey;
        return new HasMany($related, $foreignKey, $this->attributes[$key] ?? null);
    }

    /** @param class-string<Model> $related */
    protected function hasOne(string $related, string $foreignKey, ?string $localKey = null): HasOne
    {
        $key = $localKey ?? $this->primaryKey;
        return new HasOne($related, $foreignKey, $this->attributes[$key] ?? null);
    }

    /** @param class-string<Model> $related */
    protected function belongsTo(string $related, string $foreignKey, string $ownerKey = 'id'): BelongsTo
    {
        return new BelongsTo($related, $ownerKey, $this->attributes[$foreignKey] ?? null);
    }

    /** @param class-string<Model> $related */
    protected function belongsToMany(
        string $related,
        string $pivotTable,
        string $foreignPivotKey,
        string $relatedPivotKey,
        ?string $parentKey = null,
        ?string $relatedKey = null,
    ): BelongsToMany {
        $parentKey ??= $this->primaryKey;
        $relatedKey ??= (new $related())->getPrimaryKeyName();

        return new BelongsToMany(
            $related,
            $pivotTable,
            $foreignPivotKey,
            $relatedPivotKey,
            $this->attributes[$parentKey] ?? null,
            $relatedKey,
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $visible = array_diff_key($this->attributes, array_flip($this->hidden));

        foreach ($visible as $key => $value) {
            $visible[$key] = $this->serializeForArray($value);
        }

        return $visible;
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

        $this->attributes[$offset] = $this->castValue($offset, $value);
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
            throw new RuntimeException(
                static::class . ' has no fillable fields. Define $fillable or use db() for explicit database writes.'
            );
        }

        return array_intersect_key($data, array_flip($this->fillable));
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    private function applyCreateTimestamps(array $data): array
    {
        if (!$this->timestamps) {
            return $data;
        }

        $now = gmdate('Y-m-d H:i:s');
        $data[$this->createdAt] ??= $now;
        $data[$this->updatedAt] ??= $now;
        return $data;
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    private function applyUpdateTimestamp(array $data): array
    {
        if ($this->timestamps) {
            $data[$this->updatedAt] = gmdate('Y-m-d H:i:s');
        }

        return $data;
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    private function castAttributes(array $data): array
    {
        foreach ($data as $key => $value) {
            $data[$key] = $this->castValue((string) $key, $value);
        }

        return $data;
    }

    private function castValue(string $key, mixed $value): mixed
    {
        if ($value === null || !isset($this->casts[$key])) {
            return $value;
        }

        $type = strtolower($this->casts[$key]);

        return match ($type) {
            'int', 'integer' => (int) $value,
            'float', 'double', 'real' => (float) $value,
            'bool', 'boolean' => is_string($value)
                ? filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? (bool) $value
                : (bool) $value,
            'string' => (string) $value,
            'array', 'json' => $this->castJson($value),
            'datetime' => $value instanceof DateTimeInterface ? DateTimeImmutable::createFromInterface($value) : new DateTimeImmutable((string) $value),
            default => throw new RuntimeException("Unsupported model cast type: {$type}"),
        };
    }

    private function castJson(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (!is_string($value) || $value === '') {
            return [];
        }

        try {
            $decoded = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('Invalid JSON model attribute.', 0, $exception);
        }

        return is_array($decoded) ? $decoded : [];
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    private function serializeForDatabase(array $data): array
    {
        foreach ($data as $key => $value) {
            $type = strtolower((string) ($this->casts[(string) $key] ?? ''));

            if ($value === null) {
                continue;
            }

            if (in_array($type, ['array', 'json'], true)) {
                $data[$key] = json_encode($this->castJson($value), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            } elseif (in_array($type, ['bool', 'boolean'], true)) {
                $data[$key] = $this->castValue((string) $key, $value) ? 1 : 0;
            } elseif ($type === 'datetime') {
                $date = $this->castValue((string) $key, $value);
                $data[$key] = $date instanceof DateTimeInterface ? $date->format('Y-m-d H:i:s') : $value;
            } else {
                $data[$key] = $this->castValue((string) $key, $value);
            }
        }

        return $data;
    }

    private function serializeForArray(mixed $value): mixed
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format(DATE_ATOM);
        }

        if ($value instanceof self) {
            return $value->toArray();
        }

        if (is_array($value)) {
            return array_map(fn (mixed $item): mixed => $this->serializeForArray($item), $value);
        }

        return $value;
    }

    /** @param list<static> $models */
    private static function eagerLoad(array $models, string $name, ?callable $constraint = null): void
    {
        [$name, $nested] = array_pad(explode('.', $name, 2), 2, null);
        if ($models === [] || $name === '' || !is_callable([$models[0], $name])) {
            throw new RuntimeException('Unknown model relation: ' . $name);
        }

        $relations = array_map(static fn (Model $model): mixed => $model->{$name}(), $models);
        $first = $relations[0];

        if ($first instanceof HasMany || $first instanceof HasOne) {
            $values = array_values(array_unique(array_filter(
                array_map(static fn (HasMany|HasOne $relation): mixed => $relation->localValue(), $relations),
                static fn (mixed $value): bool => $value !== null
            ), SORT_REGULAR));

            $class = $first->relatedClass();
            $query = $class::query()->whereIn($first->foreignKey(), $values);
            $query = static::applyEagerConstraint($query, $constraint);
            $rows = $values === [] ? [] : $query->get();
            $grouped = [];

            foreach ($rows as $row) {
                $key = (string) ($row[$first->foreignKey()] ?? '');
                if ($first instanceof HasOne) {
                    $grouped[$key] ??= new $class($row);
                } else {
                    $grouped[$key][] = new $class($row);
                }
            }

            foreach ($models as $index => $model) {
                $key = (string) $relations[$index]->localValue();
                $model->setRelation(
                    $name,
                    $first instanceof HasOne ? ($grouped[$key] ?? null) : ($grouped[$key] ?? [])
                );
            }

            if ($nested !== null && $rows !== []) {
                $children = [];
                foreach ($models as $model) {
                    $related = $model->get($name);
                    if ($related instanceof Model) {
                        $children[] = $related;
                    } elseif (is_array($related)) {
                        array_push($children, ...$related);
                    }
                }
                if ($children !== []) {
                    $class::eagerLoad($children, $nested);
                }
            }
            return;
        }

        if ($first instanceof BelongsTo) {
            $values = array_values(array_unique(array_filter(
                array_map(static fn (BelongsTo $relation): mixed => $relation->foreignValue(), $relations),
                static fn (mixed $value): bool => $value !== null
            ), SORT_REGULAR));

            $class = $first->relatedClass();
            $query = $class::query()->whereIn($first->ownerKey(), $values);
            $query = static::applyEagerConstraint($query, $constraint);
            $rows = $values === [] ? [] : $query->get();
            $indexed = [];

            foreach ($rows as $row) {
                $indexed[(string) ($row[$first->ownerKey()] ?? '')] = new $class($row);
            }

            foreach ($models as $index => $model) {
                $model->setRelation($name, $indexed[(string) $relations[$index]->foreignValue()] ?? null);
            }

            if ($nested !== null && $indexed !== []) {
                $class::eagerLoad(array_values($indexed), $nested);
            }
            return;
        }

        if ($first instanceof BelongsToMany) {
            $parentValues = array_values(array_unique(array_filter(
                array_map(static fn (BelongsToMany $relation): mixed => $relation->parentValue(), $relations),
                static fn (mixed $value): bool => $value !== null
            ), SORT_REGULAR));

            $pivotRows = $parentValues === []
                ? []
                : Database::table($first->pivotTable())
                    ->whereIn($first->foreignPivotKey(), $parentValues)
                    ->get();

            $relatedIds = array_values(array_unique(array_filter(
                array_map(
                    static fn (array $row): mixed => $row[$first->relatedPivotKey()] ?? null,
                    $pivotRows
                ),
                static fn (mixed $value): bool => $value !== null
            ), SORT_REGULAR));

            $class = $first->relatedClass();
            $query = $class::query()->whereIn($first->relatedKey(), $relatedIds);
            $query = static::applyEagerConstraint($query, $constraint);
            $rows = $relatedIds === [] ? [] : $query->get();
            $indexed = [];

            foreach ($rows as $row) {
                $indexed[(string) ($row[$first->relatedKey()] ?? '')] = $row;
            }

            $grouped = [];
            foreach ($pivotRows as $pivotRow) {
                $relatedId = (string) ($pivotRow[$first->relatedPivotKey()] ?? '');
                $parentId = (string) ($pivotRow[$first->foreignPivotKey()] ?? '');

                if (!isset($indexed[$relatedId])) {
                    continue;
                }

                $relatedModel = new $class($indexed[$relatedId]);
                $relatedModel->setRelation('pivot', $first->pivotData($pivotRow));
                $grouped[$parentId][] = $relatedModel;
            }

            foreach ($models as $index => $model) {
                $model->setRelation($name, $grouped[(string) $relations[$index]->parentValue()] ?? []);
            }

            if ($nested !== null && $grouped !== []) {
                $children = [];
                foreach ($models as $model) {
                    array_push($children, ...$model->get($name, []));
                }
                if ($children !== []) {
                    $class::eagerLoad($children, $nested);
                }
            }
            return;
        }

        throw new RuntimeException('Relation must return HasMany, HasOne, BelongsTo or BelongsToMany: ' . $name);
    }

    private static function applyEagerConstraint(QueryBuilder $query, ?callable $constraint): QueryBuilder
    {
        if ($constraint === null) {
            return $query;
        }

        $result = $constraint($query);
        return $result instanceof QueryBuilder ? $result : $query;
    }
}
