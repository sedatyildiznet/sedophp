<?php

declare(strict_types=1);

namespace SedoPHP\Cache;

use RuntimeException;

final class Cache
{
    private static ?CacheDriverInterface $driver = null;

    /** @param array<string,mixed> $config */
    public static function configure(array $config, string $basePath): void
    {
        $path = (string) ($config['path'] ?? 'storage/cache');
        $isAbsolute = str_starts_with($path, '/')
            || preg_match('/^[A-Za-z]:[\\\\\/]/', $path) === 1;

        $directory = $isAbsolute
            ? rtrim($path, '/\\')
            : rtrim($basePath, '/\\') . DIRECTORY_SEPARATOR . trim($path, '/\\');

        self::$driver = new FileCacheDriver(
            $directory,
            (string) ($config['prefix'] ?? 'sedo_')
        );
    }

    public static function useDriver(CacheDriverInterface $driver): void
    {
        self::$driver = $driver;
    }

    public static function driver(): CacheDriverInterface
    {
        return self::$driver ?? throw new RuntimeException('Cache has not been configured.');
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return self::driver()->get($key, $default);
    }

    public static function has(string $key): bool
    {
        return self::driver()->has($key);
    }

    public static function put(string $key, mixed $value, ?int $ttlSeconds = null): void
    {
        self::driver()->put($key, $value, $ttlSeconds);
    }

    public static function add(string $key, mixed $value, int $ttlSeconds): bool
    {
        return self::driver()->add($key, $value, $ttlSeconds);
    }

    public static function remember(string $key, int $ttlSeconds, callable $callback): mixed
    {
        return self::driver()->remember($key, $ttlSeconds, $callback);
    }

    public static function forget(string $key): void
    {
        self::driver()->forget($key);
    }

    public static function pull(string $key, mixed $default = null): mixed
    {
        return self::driver()->pull($key, $default);
    }

    public static function clear(): int
    {
        return self::driver()->clear();
    }

    public static function increment(string $key, int $amount = 1, int $ttlSeconds = 60): int
    {
        return self::driver()->increment($key, $amount, $ttlSeconds);
    }
}
