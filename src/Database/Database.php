<?php

declare(strict_types=1);

namespace SedoPHP\Database;

use InvalidArgumentException;
use PDO;
use RuntimeException;
use Throwable;

final class Database
{
    /** @var array<string, mixed> */
    private static array $config = [];
    private static ?PDO $pdo = null;

    /** @param array<string, mixed> $config */
    public static function configure(array $config): void
    {
        self::$config = $config;
        self::$pdo = null;
    }

    public static function pdo(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        $driver = (string) (self::$config['driver'] ?? 'mysql');
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_STRINGIFY_FETCHES => false,
        ];

        if ($driver === 'mysql') {
            $host = (string) (self::$config['host'] ?? 'localhost');
            $port = (int) (self::$config['port'] ?? 3306);
            $database = (string) (self::$config['database'] ?? '');
            $charset = (string) (self::$config['charset'] ?? 'utf8mb4');
            $dsn = "mysql:host={$host};port={$port};dbname={$database};charset={$charset}";
            $options[PDO::ATTR_EMULATE_PREPARES] = false;
            self::$pdo = new PDO($dsn, (string) (self::$config['username'] ?? ''), (string) (self::$config['password'] ?? ''), $options);
            return self::$pdo;
        }

        if ($driver === 'sqlite') {
            $file = (string) (self::$config['sqlite'] ?? ':memory:');
            self::$pdo = new PDO('sqlite:' . $file, null, null, $options);
            return self::$pdo;
        }

        throw new InvalidArgumentException("Unsupported database driver: {$driver}");
    }

    public static function table(string $table): QueryBuilder
    {
        return new QueryBuilder(self::pdo(), $table);
    }

    public static function transaction(callable $callback): mixed
    {
        $pdo = self::pdo();
        $pdo->beginTransaction();
        try {
            $result = $callback($pdo);
            $pdo->commit();
            return $result;
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
    }

    public static function driver(): string
    {
        return (string) (self::$config['driver'] ?? 'mysql');
    }
}
