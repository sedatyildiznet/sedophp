<?php

declare(strict_types=1);

namespace SedoPHP\Database;

use InvalidArgumentException;
use RuntimeException;

final class Blueprint
{
    /** @var list<ColumnDefinition> */
    private array $columns = [];

    /** @var list<array{columns:list<string>,unique:bool,name:?string}> */
    private array $indexes = [];

    /** @var list<string> */
    private array $dropColumns = [];

    public function __construct(
        private readonly string $table,
        private readonly bool $creating = false,
    ) {
        self::assertIdentifier($table);
    }

    public function id(string $name = 'id'): ColumnDefinition
    {
        return $this->bigInteger($name)->unsigned()->autoIncrement()->primary();
    }

    public function string(string $name, int $length = 255): ColumnDefinition
    {
        if ($length < 1) {
            throw new InvalidArgumentException('String length must be at least 1.');
        }
        return $this->addColumn($name, 'string', ['length' => $length]);
    }

    public function text(string $name): ColumnDefinition
    {
        return $this->addColumn($name, 'text');
    }

    public function integer(string $name): ColumnDefinition
    {
        return $this->addColumn($name, 'integer');
    }

    public function bigInteger(string $name): ColumnDefinition
    {
        return $this->addColumn($name, 'bigInteger');
    }

    public function foreignId(string $name): ColumnDefinition
    {
        return $this->bigInteger($name)->unsigned();
    }

    public function boolean(string $name): ColumnDefinition
    {
        return $this->addColumn($name, 'boolean');
    }

    public function decimal(string $name, int $precision = 10, int $scale = 2): ColumnDefinition
    {
        if ($precision < 1 || $scale < 0 || $scale > $precision) {
            throw new InvalidArgumentException('Invalid decimal precision or scale.');
        }
        return $this->addColumn($name, 'decimal', ['precision' => $precision, 'scale' => $scale]);
    }

    public function timestamp(string $name): ColumnDefinition
    {
        return $this->addColumn($name, 'timestamp');
    }

    public function dateTime(string $name): ColumnDefinition
    {
        return $this->addColumn($name, 'datetime');
    }

    public function date(string $name): ColumnDefinition
    {
        return $this->addColumn($name, 'date');
    }

    public function json(string $name): ColumnDefinition
    {
        return $this->addColumn($name, 'json');
    }

    public function timestamps(): void
    {
        $this->timestamp('created_at')->nullable();
        $this->timestamp('updated_at')->nullable();
    }

    /** @param string|list<string> $columns */
    public function index(string|array $columns, ?string $name = null): void
    {
        $this->addIndex($columns, false, $name);
    }

    /** @param string|list<string> $columns */
    public function unique(string|array $columns, ?string $name = null): void
    {
        $this->addIndex($columns, true, $name);
    }

    public function dropColumn(string ...$columns): void
    {
        foreach ($columns as $column) {
            self::assertIdentifier($column);
            if (!in_array($column, $this->dropColumns, true)) {
                $this->dropColumns[] = $column;
            }
        }
    }

    /** @return list<string> */
    public function statements(string $driver): array
    {
        if (!in_array($driver, ['mysql', 'sqlite'], true)) {
            throw new RuntimeException("Unsupported schema driver: {$driver}");
        }

        $statements = [];

        if ($this->creating) {
            if ($this->columns === []) {
                throw new RuntimeException('A new table must define at least one column.');
            }

            $columns = array_map(fn (ColumnDefinition $column): string => $this->compileColumn($column, $driver), $this->columns);
            $suffix = $driver === 'mysql' ? ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4' : '';
            $statements[] = 'CREATE TABLE ' . $this->quote($this->table, $driver)
                . ' (' . implode(', ', $columns) . ')' . $suffix;
        } else {
            foreach ($this->columns as $column) {
                if ($column->isPrimary() || $column->isAutoIncrement()) {
                    throw new RuntimeException('Primary or auto-increment columns cannot be added with Schema::table().');
                }

                $statements[] = 'ALTER TABLE ' . $this->quote($this->table, $driver)
                    . ' ADD COLUMN ' . $this->compileColumn($column, $driver);
            }

            foreach ($this->dropColumns as $column) {
                $statements[] = 'ALTER TABLE ' . $this->quote($this->table, $driver)
                    . ' DROP COLUMN ' . $this->quote($column, $driver);
            }
        }

        foreach ($this->columns as $column) {
            if ($column->isUnique()) {
                $this->indexes[] = [
                    'columns' => [$column->name],
                    'unique' => true,
                    'name' => null,
                ];
            }
        }

        foreach ($this->indexes as $index) {
            $name = $index['name'] ?? $this->indexName($index['columns'], $index['unique']);
            self::assertIdentifier($name);

            $statements[] = 'CREATE ' . ($index['unique'] ? 'UNIQUE ' : '')
                . 'INDEX ' . $this->quote($name, $driver)
                . ' ON ' . $this->quote($this->table, $driver)
                . ' (' . implode(', ', array_map(fn (string $column): string => $this->quote($column, $driver), $index['columns'])) . ')';
        }

        return $statements;
    }

    /** @param array<string,int> $options */
    private function addColumn(string $name, string $type, array $options = []): ColumnDefinition
    {
        self::assertIdentifier($name);

        foreach ($this->columns as $column) {
            if ($column->name === $name) {
                throw new InvalidArgumentException("Duplicate schema column: {$name}");
            }
        }

        $column = new ColumnDefinition($name, $type, $options);
        $this->columns[] = $column;
        return $column;
    }

    /** @param string|list<string> $columns */
    private function addIndex(string|array $columns, bool $unique, ?string $name): void
    {
        $columns = is_array($columns) ? array_values($columns) : [$columns];
        if ($columns === []) {
            throw new InvalidArgumentException('Index must contain at least one column.');
        }

        foreach ($columns as $column) {
            self::assertIdentifier($column);
        }

        if ($name !== null) {
            self::assertIdentifier($name);
        }

        $this->indexes[] = ['columns' => $columns, 'unique' => $unique, 'name' => $name];
    }

    private function compileColumn(ColumnDefinition $column, string $driver): string
    {
        $name = $this->quote($column->name, $driver);

        if ($driver === 'sqlite' && $column->isPrimary() && $column->isAutoIncrement()) {
            return $name . ' INTEGER PRIMARY KEY AUTOINCREMENT';
        }

        $type = $this->compileType($column, $driver);
        $sql = $name . ' ' . $type;

        if ($driver === 'mysql' && $column->isUnsigned() && in_array($column->type, ['integer', 'bigInteger'], true)) {
            $sql .= ' UNSIGNED';
        }

        if ($column->isAutoIncrement()) {
            $sql .= $driver === 'mysql' ? ' AUTO_INCREMENT' : '';
        }

        if ($column->isPrimary()) {
            $sql .= ' PRIMARY KEY';
        }

        $sql .= $column->isNullable() ? ' NULL' : ' NOT NULL';

        if ($column->usesCurrentTimestamp()) {
            $sql .= ' DEFAULT CURRENT_TIMESTAMP';
        } elseif ($column->hasDefault()) {
            $sql .= ' DEFAULT ' . $this->literal($column->default());
        }

        return $sql;
    }

    private function compileType(ColumnDefinition $column, string $driver): string
    {
        if ($driver === 'sqlite') {
            return match ($column->type) {
                'integer', 'bigInteger', 'boolean' => 'INTEGER',
                'decimal' => 'NUMERIC',
                default => 'TEXT',
            };
        }

        return match ($column->type) {
            'string' => 'VARCHAR(' . ($column->options['length'] ?? 255) . ')',
            'text' => 'TEXT',
            'integer' => 'INT',
            'bigInteger' => 'BIGINT',
            'boolean' => 'TINYINT(1)',
            'decimal' => 'DECIMAL(' . ($column->options['precision'] ?? 10) . ',' . ($column->options['scale'] ?? 2) . ')',
            'timestamp' => 'TIMESTAMP',
            'datetime' => 'DATETIME',
            'date' => 'DATE',
            'json' => 'JSON',
            default => throw new RuntimeException("Unknown schema column type: {$column->type}"),
        };
    }

    private function literal(mixed $value): string
    {
        return match (true) {
            $value === null => 'NULL',
            is_bool($value) => $value ? '1' : '0',
            is_int($value), is_float($value) => (string) $value,
            default => "'" . str_replace("'", "''", (string) $value) . "'",
        };
    }

    /** @param list<string> $columns */
    private function indexName(array $columns, bool $unique): string
    {
        return strtolower($this->table . '_' . implode('_', $columns) . ($unique ? '_unique' : '_index'));
    }

    private function quote(string $identifier, string $driver): string
    {
        self::assertIdentifier($identifier);
        $quote = $driver === 'mysql' ? chr(96) : '"';
        return $quote . $identifier . $quote;
    }

    private static function assertIdentifier(string $identifier): void
    {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $identifier) !== 1) {
            throw new InvalidArgumentException("Invalid schema identifier: {$identifier}");
        }
    }
}
