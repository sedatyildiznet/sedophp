<?php

declare(strict_types=1);

namespace SedoPHP\Database;

use InvalidArgumentException;
use PDO;
use PDOException;
use RuntimeException;
use SedoPHP\Core\Logger;
use Throwable;

final class Database
{
    /** @var array<string, mixed> */
    private static array $config = [];
    private static ?PDO $pdo = null;
    private static int $transactionDepth = 0;
    private static bool $logQueries = false;
    private static int $slowQueryMs = 0;

    /** @param array<string, mixed> $config */
    public static function configure(array $config): void
    {
        self::$config = $config;
        self::$pdo = null;
        self::$transactionDepth = 0;
        self::$logQueries = (bool) ($config['log_queries'] ?? false);
        self::$slowQueryMs = max(0, (int) ($config['slow_query_ms'] ?? 0));
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

            if ((bool) (self::$config['foreign_keys'] ?? true)) {
                self::$pdo->exec('PRAGMA foreign_keys = ON');
            }

            return self::$pdo;
        }

        throw new InvalidArgumentException("Unsupported database driver: {$driver}");
    }

    public static function table(string $table): QueryBuilder
    {
        return new QueryBuilder(self::pdo(), $table);
    }

    public static function transaction(callable $callback, int $attempts = 1): mixed
    {
        if ($attempts < 1) {
            throw new InvalidArgumentException('Transaction attempts must be at least 1.');
        }

        $pdo = self::pdo();

        if (self::$transactionDepth > 0) {
            return self::nestedTransaction($pdo, $callback);
        }

        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            $pdo->beginTransaction();
            self::$transactionDepth = 1;

            try {
                $result = $callback($pdo);
                $pdo->commit();
                self::$transactionDepth = 0;
                return $result;
            } catch (Throwable $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                self::$transactionDepth = 0;

                if ($attempt < $attempts && self::isRetryableTransactionError($exception)) {
                    usleep(50000 * $attempt);
                    continue;
                }

                throw $exception;
            }
        }

        throw new RuntimeException('Transaction failed without returning or throwing.');
    }

    private static function nestedTransaction(PDO $pdo, callable $callback): mixed
    {
        $savepoint = 'sedo_sp_' . self::$transactionDepth;
        $pdo->exec('SAVEPOINT ' . $savepoint);
        self::$transactionDepth++;

        try {
            $result = $callback($pdo);
            self::$transactionDepth--;
            $pdo->exec('RELEASE SAVEPOINT ' . $savepoint);
            return $result;
        } catch (Throwable $exception) {
            self::$transactionDepth--;
            $pdo->exec('ROLLBACK TO SAVEPOINT ' . $savepoint);
            $pdo->exec('RELEASE SAVEPOINT ' . $savepoint);
            throw $exception;
        }
    }

    private static function isRetryableTransactionError(Throwable $exception): bool
    {
        if (!$exception instanceof PDOException) {
            return false;
        }

        $sqlState = (string) $exception->getCode();
        $driverCode = (int) ($exception->errorInfo[1] ?? 0);

        if (in_array($sqlState, ['40001', 'HY000'], true) && in_array($driverCode, [5, 6, 1205, 1213], true)) {
            return true;
        }

        return $sqlState === '40001';
    }

    public static function diagnosticsEnabled(): bool
    {
        return self::$logQueries || self::$slowQueryMs > 0;
    }

    public static function recordQuery(string $sql, int $bindingCount, float $durationMs): void
    {
        $slow = self::$slowQueryMs > 0 && $durationMs >= self::$slowQueryMs;

        if (!self::$logQueries && !$slow) {
            return;
        }

        $context = [
            'driver' => self::driver(),
            'sql' => $sql,
            'binding_count' => $bindingCount,
            'duration_ms' => round($durationMs, 3),
            'slow' => $slow,
        ];

        if ($slow) {
            Logger::warning('Slow database query', $context);
            return;
        }

        Logger::info('Database query', $context);
    }

    public static function driver(): string
    {
        return (string) (self::$config['driver'] ?? 'mysql');
    }
}
