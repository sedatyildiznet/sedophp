<?php

declare(strict_types=1);

namespace SedoPHP\Queue;

use RuntimeException;
use SedoPHP\Database\Database;
use Throwable;

final class Queue
{
    /** @param class-string<JobInterface> $job @param array<string,mixed> $payload */
    public static function push(
        string $job,
        array $payload = [],
        int $delaySeconds = 0,
        int $maxAttempts = 3,
    ): int {
        if (!class_exists($job) || !is_subclass_of($job, JobInterface::class)) {
            throw new RuntimeException("Queue job must implement JobInterface: {$job}");
        }

        return Database::table('jobs')->insert([
            'job' => $job,
            'payload' => json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'attempts' => 0,
            'max_attempts' => max(1, $maxAttempts),
            'available_at' => gmdate('Y-m-d H:i:s', time() + max(0, $delaySeconds)),
            'reserved_at' => null,
            'failed_at' => null,
            'last_error' => null,
            'created_at' => gmdate('Y-m-d H:i:s'),
        ]);
    }

    public static function work(int $limit = 10): int
    {
        $limit = max(1, $limit);
        $rows = Database::table('jobs')
            ->whereNull('reserved_at')
            ->whereNull('failed_at')
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
                $data['available_at'] = gmdate('Y-m-d H:i:s', time() + min(300, 30 * $attempts));
            }

            Database::table('jobs')->where('id', $id)->update($data);
            return true;
        }
    }
}
