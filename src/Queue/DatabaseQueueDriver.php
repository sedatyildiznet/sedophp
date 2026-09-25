<?php

declare(strict_types=1);

namespace SedoPHP\Queue;

use SedoPHP\Database\Database;

final class DatabaseQueueDriver implements QueueDriverInterface
{
    /** @param array<string,mixed> $record */
    public function push(array $record): int
    {
        return Database::table('jobs')->insert($record);
    }

    /** @return array<string,mixed>|null */
    public function findByUniqueKey(string $uniqueKey): ?array
    {
        return Database::table('jobs')
            ->where('unique_key', $uniqueKey)
            ->first();
    }

    /** @return list<array<string,mixed>> */
    public function due(string $queue, int $limit, string $now): array
    {
        return Database::table('jobs')
            ->whereNull('reserved_at')
            ->whereNull('failed_at')
            ->where('queue', $queue)
            ->where('available_at', '<=', $now)
            ->orderBy('id')
            ->limit(max(1, $limit))
            ->get();
    }

    public function releaseStale(string $cutoff): int
    {
        return Database::table('jobs')
            ->whereNotNull('reserved_at')
            ->whereNull('failed_at')
            ->where('reserved_at', '<=', $cutoff)
            ->update(['reserved_at' => null]);
    }

    public function reserve(int|string $id, string $reservedAt): bool
    {
        return Database::table('jobs')
            ->where('id', $id)
            ->whereNull('reserved_at')
            ->whereNull('failed_at')
            ->update(['reserved_at' => $reservedAt]) === 1;
    }

    /** @param array<string,mixed> $data */
    public function update(int|string $id, array $data): int
    {
        return Database::table('jobs')->where('id', $id)->update($data);
    }

    public function delete(int|string $id): int
    {
        return Database::table('jobs')->where('id', $id)->delete();
    }

    /** @return list<array<string,mixed>> */
    public function failed(int $limit): array
    {
        return Database::table('jobs')
            ->whereNotNull('failed_at')
            ->orderBy('id', 'desc')
            ->limit(max(1, $limit))
            ->get();
    }

    public function retryFailed(int|string|null $id, string $availableAt): int
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
            'available_at' => $availableAt,
        ]);
    }

    public function flushFailed(): int
    {
        $rows = Database::table('jobs')
            ->whereNotNull('failed_at')
            ->select('id')
            ->get();

        $deleted = 0;
        foreach ($rows as $row) {
            $id = $row['id'] ?? null;
            if ($id !== null) {
                $deleted += $this->delete($id);
            }
        }

        return $deleted;
    }
}
