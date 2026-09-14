<?php

declare(strict_types=1);

namespace SedoPHP\Database;

use PDO;
use RuntimeException;

final class MigrationRunner
{
    public function __construct(private readonly string $directory)
    {
    }

    public function migrate(): int
    {
        $pdo = Database::pdo();
        $this->ensureTable($pdo);
        $done = $pdo->query('SELECT migration FROM sedo_migrations')->fetchAll(PDO::FETCH_COLUMN);
        $done = array_flip(array_map('strval', $done));
        $batch = (int) $pdo->query('SELECT COALESCE(MAX(batch), 0) FROM sedo_migrations')->fetchColumn() + 1;
        $count = 0;

        foreach ($this->files() as $file) {
            $name = basename($file);
            if (isset($done[$name])) {
                continue;
            }
            $migration = require $file;
            if (!is_array($migration) || !isset($migration['up']) || !is_callable($migration['up'])) {
                throw new RuntimeException("Invalid migration: {$name}");
            }

            Database::transaction(function (PDO $db) use ($migration, $name, $batch): void {
                $migration['up']($db);
                $statement = $db->prepare('INSERT INTO sedo_migrations (migration, batch) VALUES (?, ?)');
                $statement->execute([$name, $batch]);
            });
            $count++;
        }
        return $count;
    }

    public function rollback(): int
    {
        $pdo = Database::pdo();
        $this->ensureTable($pdo);
        $batch = (int) $pdo->query('SELECT COALESCE(MAX(batch), 0) FROM sedo_migrations')->fetchColumn();
        if ($batch === 0) {
            return 0;
        }

        $statement = $pdo->prepare('SELECT migration FROM sedo_migrations WHERE batch = ? ORDER BY id DESC');
        $statement->execute([$batch]);
        $names = $statement->fetchAll(PDO::FETCH_COLUMN);
        $count = 0;

        foreach ($names as $name) {
            $file = $this->directory . '/' . basename((string) $name);
            if (!is_file($file)) {
                throw new RuntimeException("Migration file missing: {$name}");
            }
            $migration = require $file;
            if (!is_array($migration) || !isset($migration['down']) || !is_callable($migration['down'])) {
                throw new RuntimeException("Migration cannot be rolled back: {$name}");
            }

            Database::transaction(function (PDO $db) use ($migration, $name): void {
                $migration['down']($db);
                $delete = $db->prepare('DELETE FROM sedo_migrations WHERE migration = ?');
                $delete->execute([$name]);
            });
            $count++;
        }
        return $count;
    }

    /** @return list<string> */
    private function files(): array
    {
        $files = glob(rtrim($this->directory, '/') . '/*.php') ?: [];
        sort($files, SORT_STRING);
        return array_values($files);
    }

    private function ensureTable(PDO $pdo): void
    {
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        $sql = $driver === 'mysql'
            ? 'CREATE TABLE IF NOT EXISTS sedo_migrations (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, migration VARCHAR(255) NOT NULL UNIQUE, batch INT NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
            : 'CREATE TABLE IF NOT EXISTS sedo_migrations (id INTEGER PRIMARY KEY AUTOINCREMENT, migration TEXT NOT NULL UNIQUE, batch INTEGER NOT NULL)';
        $pdo->exec($sql);
    }
}
