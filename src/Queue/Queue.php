<?php

declare(strict_types=1);

namespace SedoPHP\Queue;

use PDOException;
use RuntimeException;
use Throwable;

final class Queue
{
    private static int $retryAfter = 300;
    private static ?QueueDriverInterface $driver = null;

    /** @param array<string,mixed> $config */
    public static function configure(array $config): void
    {
        self::$retryAfter = max(30, (int) ($config['retry_after'] ?? 300));
        self::$driver ??= new DatabaseQueueDriver();
    }

    public static function useDriver(QueueDriverInterface $driver): void
    {
        self::$driver = $driver;
    }

    public static function driver(): QueueDriverInterface
    {
        return self::$driver ??= new DatabaseQueueDriver();
    }

    /** @param class-string<JobInterface> $job @param array<string,mixed> $payload */
    public static function push(
        string $job,
        array $payload = [],
        int $delaySeconds = 0,
        int $maxAttempts = 3,
        string $queue = 'default',
        int $backoffSeconds = 30,
        int $timeoutSeconds = 60,
        ?string $uniqueKey = null,
        string $backoffStrategy = 'linear',
    ): int {
        if (!class_exists($job) || !is_subclass_of($job, JobInterface::class)) {
            throw new RuntimeException("Queue job must implement JobInterface: {$job}");
        }

        $backoffStrategy = strtolower(trim($backoffStrategy));
        if (!in_array($backoffStrategy, ['linear', 'exponential'], true)) {
            throw new RuntimeException('Queue backoff strategy must be linear or exponential.');
        }

        $uniqueKey = $uniqueKey !== null ? trim($uniqueKey) : null;
        if ($uniqueKey === '') {
            $uniqueKey = null;
        }

        if ($uniqueKey !== null) {
            $existing = self::driver()->findByUniqueKey($uniqueKey);
            if ($existing !== null && isset($existing['id'])) {
                return (int) $existing['id'];
            }
        }

        $record = [
            'job' => $job,
            'payload' => json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'attempts' => 0,
            'max_attempts' => max(1, $maxAttempts),
            'queue' => $queue !== '' ? $queue : 'default',
            'backoff' => max(1, $backoffSeconds),
            'backoff_strategy' => $backoffStrategy,
            'timeout' => max(1, $timeoutSeconds),
            'unique_key' => $uniqueKey,
            'available_at' => gmdate('Y-m-d H:i:s', time() + max(0, $delaySeconds)),
            'reserved_at' => null,
            'failed_at' => null,
            'last_error' => null,
            'created_at' => gmdate('Y-m-d H:i:s'),
        ];

        try {
            return self::driver()->push($record);
        } catch (PDOException $exception) {
            if ($uniqueKey !== null) {
                $existing = self::driver()->findByUniqueKey($uniqueKey);
                if ($existing !== null && isset($existing['id'])) {
                    return (int) $existing['id'];
                }
            }

            throw $exception;
        }
    }

    /** @param class-string<JobInterface> $job @param array<string,mixed> $payload */
    public static function pushUnique(
        string $uniqueKey,
        string $job,
        array $payload = [],
        int $delaySeconds = 0,
        int $maxAttempts = 3,
        string $queue = 'default',
        int $backoffSeconds = 30,
        int $timeoutSeconds = 60,
        string $backoffStrategy = 'linear',
    ): int {
        return self::push(
            $job,
            $payload,
            $delaySeconds,
            $maxAttempts,
            $queue,
            $backoffSeconds,
            $timeoutSeconds,
            $uniqueKey,
            $backoffStrategy,
        );
    }

    public static function work(int $limit = 10, string $queue = 'default'): int
    {
        $limit = max(1, $limit);
        self::releaseStaleReservations();

        $rows = self::driver()->due(
            $queue,
            $limit,
            gmdate('Y-m-d H:i:s')
        );

        $processed = 0;
        foreach ($rows as $row) {
            if (self::process($row)) {
                $processed++;
            }
        }

        return $processed;
    }

    public static function releaseStaleReservations(): int
    {
        return self::driver()->releaseStale(
            gmdate('Y-m-d H:i:s', time() - self::$retryAfter)
        );
    }

    /** @return list<array<string,mixed>> */
    public static function failed(int $limit = 50): array
    {
        return self::driver()->failed(max(1, $limit));
    }

    public static function retryFailed(int|string|null $id = null): int
    {
        return self::driver()->retryFailed(
            $id,
            gmdate('Y-m-d H:i:s')
        );
    }

    public static function flushFailed(): int
    {
        return self::driver()->flushFailed();
    }

    /** @param array<string,mixed> $row */
    private static function process(array $row): bool
    {
        $id = $row['id'] ?? null;
        if ($id === null) {
            return false;
        }

        if (!self::driver()->reserve($id, gmdate('Y-m-d H:i:s'))) {
            return false;
        }

        try {
            $startedAt = microtime(true);
            $jobClass = (string) ($row['job'] ?? '');
            if (!class_exists($jobClass) || !is_subclass_of($jobClass, JobInterface::class)) {
                throw new RuntimeException("Invalid queued job: {$jobClass}");
            }

            $payload = json_decode((string) ($row['payload'] ?? '{}'), true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($payload)) {
                throw new RuntimeException('Queue payload must decode to an array.');
            }

            $job = new $jobClass();
            $job->handle($payload);

            $timeout = max(1, (int) ($row['timeout'] ?? 60));
            if ((microtime(true) - $startedAt) > $timeout) {
                throw new RuntimeException("Queued job exceeded its {$timeout}s timeout.");
            }

            self::driver()->delete($id);
            return true;
        } catch (Throwable $exception) {
            $attempts = (int) ($row['attempts'] ?? 0) + 1;
            $maxAttempts = max(1, (int) ($row['max_attempts'] ?? 3));
            $data = [
                'attempts' => $attempts,
                'reserved_at' => null,
                'last_error' => substr($exception->getMessage(), 0, 2000),
            ];

            if ($attempts >= $maxAttempts) {
                $data['failed_at'] = gmdate('Y-m-d H:i:s');
            } else {
                $baseBackoff = max(1, (int) ($row['backoff'] ?? 30));
                $strategy = strtolower((string) ($row['backoff_strategy'] ?? 'linear'));
                $delay = self::backoffDelay($baseBackoff, $attempts, $strategy);
                $data['available_at'] = gmdate('Y-m-d H:i:s', time() + $delay);
            }

            self::driver()->update($id, $data);
            return true;
        }
    }

    private static function backoffDelay(int $baseSeconds, int $attempts, string $strategy): int
    {
        if ($strategy === 'exponential') {
            $power = min(10, max(0, $attempts - 1));
            return min(3600, $baseSeconds * (2 ** $power));
        }

        return min(3600, $baseSeconds * max(1, $attempts));
    }
}
