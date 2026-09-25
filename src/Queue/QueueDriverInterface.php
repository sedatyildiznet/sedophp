<?php

declare(strict_types=1);

namespace SedoPHP\Queue;

interface QueueDriverInterface
{
    /** @param array<string,mixed> $record */
    public function push(array $record): int;

    /** @return array<string,mixed>|null */
    public function findByUniqueKey(string $uniqueKey): ?array;

    /** @return list<array<string,mixed>> */
    public function due(string $queue, int $limit, string $now): array;

    public function releaseStale(string $cutoff): int;

    public function reserve(int|string $id, string $reservedAt): bool;

    /** @param array<string,mixed> $data */
    public function update(int|string $id, array $data): int;

    public function delete(int|string $id): int;

    /** @return list<array<string,mixed>> */
    public function failed(int $limit): array;

    public function retryFailed(int|string|null $id, string $availableAt): int;

    public function flushFailed(): int;
}
