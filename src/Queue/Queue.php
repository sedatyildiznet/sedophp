<?php

declare(strict_types=1);

namespace SedoPHP\Queue;

use RuntimeException;
use SedoPHP\Database\Database;
use Throwable;

final class Queue
{
    private static int $retryAfter = 300;

    /** @param array<string,mixed> $config */
    public static function configure(array $config): void
    {
        self::$retryAfter = max(30, (int) ($config['retry_after'] ?? 300));
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
    ): int {
        if (!class_exists($job) || !is_subclass_of($job, JobInterface::class)) {
            throw new RuntimeException("Queue job must implement JobInterface: {$job}");
        }

        return Database::table('jobs')->insert([
            'job' => $job,
            'payload' => json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'attempts' => 0,
            'max_attempts' => max(1, $maxAttempts),
            'queue' => $queue !== '' ? $queue : 'default',
            'backoff' => max(1, $backoffSeconds),
            'timeout' => max(1, $timeoutSeconds),
            'available_at' => gmdate('Y-m-d H:i:s', time() + max(0, $delaySeconds)),
            'reserved_at' => null,
            'failed_at' => null,
            'last_error' => null,
            'created_at' => gmdate('Y-m-d H:i:s'),
        ]);
    }

    public static function work(int $limit = 10, string $queue = 'default'): int
    {
        $limit = max(1, $limit);
        self::releaseStaleReservations();
        $rows = Database::table('jobs')
            ->whereNull('reserved_at')
            ->whereNull('failed_at')
            ->where('queue', $queue)
            ->where('available_at', '<=', gmdate('Y-m-d H:i:s'))
            ->orderBy('id')
            ->limit($limit)
            ->get();

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
        return Database::table('jobs')
            ->whereNotNull('reserved_at')
            ->whereNull('failed_at')
            ->where('reserved_at', '<=', gmdate('Y-m-d H:i:s', time() - self::$retryAfter))
            ->update(['reserved_at' => null]);
    }

    /** @return list<array<string,mixed>> */
    public static function failed(int $limit = 50): array
    {
        return Database::table('jobs')->whereNotNull('failed_at')->orderBy('id', 'desc')->limit(max(1, $limit))->get();
    }

    public static function retryFailed(int|string|null $id = null): int
    {
        $query = Database::table('jobs')->whereNotNull('failed_at');
        if ($id !== null) {
            $query->where('id', $id);
        }
        return $query->update([
            'attempts' => 0,
            'reserved_at' => null,
            'failed_at' => null,
            'last_error' => null,
            'available_at' => gmdate('Y-m-d H:i:s'),
        ]);
    }

    public static function flushFailed(): int
    {
        $rows = Database::table('jobs')->whereNotNull('failed_at')->select('id')->get();
        $deleted = 0;
        foreach ($rows as $row) {
            $deleted += Database::table('jobs')->where('id', $row['id'])->delete();
        }
        return $deleted;
    }

    /** @param array<string,mixed> $row */
    private static function process(array $row): bool
    {
        $id = $row['id'] ?? null;
        if ($id === null) {
            return false;
        }

        $reserved = Database::table('jobs')
            ->where('id', $id)
            ->whereNull('reserved_at')
            ->update(['reserved_at' => gmdate('Y-m-d H:i:s')]);

        if ($reserved !== 1) {
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
            Database::table('jobs')->where('id', $id)->delete();
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
                $backoff = max(1, (int) ($row['backoff'] ?? 30));
                $data['available_at'] = gmdate('Y-m-d H:i:s', time() + min(3600, $backoff * $attempts));
            }

            Database::table('jobs')->where('id', $id)->update($data);
            return true;
        }
    }
}
