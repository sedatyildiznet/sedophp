<?php

declare(strict_types=1);

namespace SedoPHP\Console;

use RuntimeException;

abstract class Command
{
    protected string $name = '';
    protected string $description = '';

    final public function name(): string
    {
        if ($this->name === '' || preg_match('/^[A-Za-z0-9][A-Za-z0-9:_-]*$/', $this->name) !== 1) {
            throw new RuntimeException(static::class . ' must define a valid command name.');
        }

        return $this->name;
    }

    final public function description(): string
    {
        return trim($this->description);
    }

    /** @param list<string> $arguments */
    abstract public function handle(array $arguments): int;
}
