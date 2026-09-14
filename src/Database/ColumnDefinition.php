<?php

declare(strict_types=1);

namespace SedoPHP\Database;

final class ColumnDefinition
{
    private bool $nullable = false;
    private bool $unsigned = false;
    private bool $primary = false;
    private bool $autoIncrement = false;
    private bool $unique = false;
    private bool $hasDefault = false;
    private mixed $default = null;
    private bool $currentTimestamp = false;

    /** @param array<string,int> $options */
    public function __construct(
        public readonly string $name,
        public readonly string $type,
        public readonly array $options = [],
    ) {
    }

    public function nullable(bool $value = true): self
    {
        $this->nullable = $value;
        return $this;
    }

    public function unsigned(bool $value = true): self
    {
        $this->unsigned = $value;
        return $this;
    }

    public function primary(bool $value = true): self
    {
        $this->primary = $value;
        return $this;
    }

    public function autoIncrement(bool $value = true): self
    {
        $this->autoIncrement = $value;
        return $this;
    }

    public function unique(bool $value = true): self
    {
        $this->unique = $value;
        return $this;
    }

    public function defaultValue(mixed $value): self
    {
        $this->hasDefault = true;
        $this->default = $value;
        return $this;
    }

    public function useCurrent(): self
    {
        $this->currentTimestamp = true;
        return $this;
    }

    public function isNullable(): bool { return $this->nullable; }
    public function isUnsigned(): bool { return $this->unsigned; }
    public function isPrimary(): bool { return $this->primary; }
    public function isAutoIncrement(): bool { return $this->autoIncrement; }
    public function isUnique(): bool { return $this->unique; }
    public function hasDefault(): bool { return $this->hasDefault; }
    public function default(): mixed { return $this->default; }
    public function usesCurrentTimestamp(): bool { return $this->currentTimestamp; }
}
