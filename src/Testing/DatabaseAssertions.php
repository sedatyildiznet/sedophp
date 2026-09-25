<?php

declare(strict_types=1);

namespace SedoPHP\Testing;

use InvalidArgumentException;
use RuntimeException;
use SedoPHP\Database\Database;

final class DatabaseAssertions
{
    /** @param array<string,mixed> $attributes */
    public static function assertHas(string $table, array $attributes): void
    {
        if ($attributes === []) {
            throw new InvalidArgumentException('Database assertion attributes cannot be empty.');
        }

        $query = Database::table($table);
        foreach ($attributes as $column => $value) {
            $query->where((string) $column, $value);
        }

        if (!$query->exists()) {
            throw new RuntimeException("Database table {$table} does not contain the expected row.");
        }
    }

    /** @param array<string,mixed> $attributes */
    public static function assertMissing(string $table, array $attributes): void
    {
        if ($attributes === []) {
            throw new InvalidArgumentException('Database assertion attributes cannot be empty.');
        }

        $query = Database::table($table);
        foreach ($attributes as $column => $value) {
            $query->where((string) $column, $value);
        }

        if ($query->exists()) {
            throw new RuntimeException("Database table {$table} contains an unexpected row.");
        }
    }
}
