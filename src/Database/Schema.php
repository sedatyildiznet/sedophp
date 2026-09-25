<?php

declare(strict_types=1);

namespace SedoPHP\Database;

use PDO;

final class Schema
{
    public static function create(string $table, callable $callback): void
    {
        $blueprint = new Blueprint($table, true);
        $callback($blueprint);
        self::execute($blueprint);
    }

    public static function table(string $table, callable $callback): void
    {
        $blueprint = new Blueprint($table, false);
        $callback($blueprint);
        self::execute($blueprint);
    }

    public static function drop(string $table): void
    {
        Database::pdo()->exec('DROP TABLE ' . self::quote($table));
    }

    public static function dropIfExists(string $table): void
    {
        Database::pdo()->exec('DROP TABLE IF EXISTS ' . self::quote($table));
    }

    public static function rename(string $from, string $to): void
    {
        Database::pdo()->exec('ALTER TABLE ' . self::quote($from) . ' RENAME TO ' . self::quote($to));
    }

    public static function hasTable(string $table): bool
    {
        $pdo = Database::pdo();

        if (Database::driver() === 'mysql') {
            $statement = $pdo->prepare(
                'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?'
            );
            $statement->execute([$table]);
            return (int) $statement->fetchColumn() > 0;
        }

        $statement = $pdo->prepare("SELECT COUNT(*) FROM sqlite_master WHERE type = 'table' AND name = ?");
        $statement->execute([$table]);
        return (int) $statement->fetchColumn() > 0;
    }

    public static function hasColumn(string $table, string $column): bool
    {
        if (!self::hasTable($table)) {
            return false;
        }

        $pdo = Database::pdo();

        if (Database::driver() === 'mysql') {
            $statement = $pdo->prepare(
                'SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?'
            );
            $statement->execute([$table, $column]);
            return (int) $statement->fetchColumn() > 0;
        }

        $statement = $pdo->query('PRAGMA table_info(' . self::quote($table) . ')');
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if (($row['name'] ?? null) === $column) {
                return true;
            }
        }

        return false;
    }

    private static function execute(Blueprint $blueprint): void
    {
        foreach ($blueprint->statements(Database::driver()) as $sql) {
            Database::pdo()->exec($sql);
        }
    }

    private static function quote(string $identifier): string
    {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $identifier) !== 1) {
            throw new \InvalidArgumentException("Invalid schema identifier: {$identifier}");
        }

        $quote = Database::driver() === 'mysql' ? chr(96) : '"';
        return $quote . $identifier . $quote;
    }
}
