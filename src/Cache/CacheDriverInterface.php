<?php

declare(strict_types=1);

namespace SedoPHP\Cache;

interface CacheDriverInterface
{
    public function get(string $key, mixed $default = null): mixed;
    public function has(string $key): bool;
    public function put(string $key, mixed $value, ?int $ttlSeconds = null): void;
    public function add(string $key, mixed $value, int $ttlSeconds): bool;
    public function remember(string $key, int $ttlSeconds, callable $callback): mixed;
    public function forget(string $key): void;
    public function pull(string $key, mixed $default = null): mixed;
    public function clear(): int;
    public function increment(string $key, int $amount = 1, int $ttlSeconds = 60): int;
}
