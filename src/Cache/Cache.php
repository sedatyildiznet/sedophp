<?php

declare(strict_types=1);

namespace SedoPHP\Cache;

use RuntimeException;

final class Cache
{
    private static string $directory = '';
    private static string $prefix = 'sedo_';

    /** @param array<string, mixed> $config */
    public static function configure(array $config, string $basePath): void
    {
        $path = (string) ($config['path'] ?? 'storage/cache');
        $isAbsolute = str_starts_with($path, '/')
            || preg_match('/^[A-Za-z]:[\\\\\/]/', $path) === 1;

        self::$directory = $isAbsolute
            ? rtrim($path, '/\\')
            : rtrim($basePath, '/\\') . DIRECTORY_SEPARATOR . trim($path, '/\\');
        self::$prefix = (string) ($config['prefix'] ?? 'sedo_');
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        [$found, $value] = self::read($key);
        return $found ? $value : $default;
    }

    public static function has(string $key): bool
    {
        return self::read($key)[0];
    }

    public static function put(string $key, mixed $value, ?int $ttlSeconds = null): void
    {
        if ($ttlSeconds !== null && $ttlSeconds < 1) {
            self::forget($key);
            return;
        }

        self::ensureDirectory();
        $expiresAt = $ttlSeconds === null ? 0 : time() + $ttlSeconds;
        self::writePayload($key, ['expires_at' => $expiresAt, 'value' => $value]);
    }

    public static function add(string $key, mixed $value, int $ttlSeconds): bool
    {
        if ($ttlSeconds < 1) {
            throw new RuntimeException('Cache add TTL must be at least one second.');
        }

        self::ensureDirectory();
        $file = self::file($key);

        for ($attempt = 0; $attempt < 2; $attempt++) {
            $handle = @fopen($file, 'x');
            if ($handle !== false) {
                try {
                    $payload = serialize(['expires_at' => time() + $ttlSeconds, 'value' => $value]);
                    if (fwrite($handle, $payload) === false) {
                        @unlink($file);
                        throw new RuntimeException('Unable to write cache file.');
                    }
                    fflush($handle);
                    return true;
                } finally {
                    fclose($handle);
                }
            }

            [$found] = self::read($key);
            if ($found) {
                return false;
            }
        }

        return false;
    }

    public static function remember(string $key, int $ttlSeconds, callable $callback): mixed
    {
        [$found, $value] = self::read($key);
        if ($found) {
            return $value;
        }

        $value = $callback();
        self::put($key, $value, $ttlSeconds);
        return $value;
    }

    public static function forget(string $key): void
    {
        $file = self::file($key);
        if (is_file($file)) {
            @unlink($file);
        }
    }

    public static function clear(): int
    {
        if (!is_dir(self::$directory)) {
            return 0;
        }

        $count = 0;
        foreach (glob(self::$directory . DIRECTORY_SEPARATOR . self::$prefix . '*.cache') ?: [] as $file) {
            if (is_file($file) && @unlink($file)) {
                $count++;
            }
        }
        return $count;
    }

    public static function increment(string $key, int $amount = 1, int $ttlSeconds = 60): int
    {
        if ($ttlSeconds < 1) {
            throw new RuntimeException('Cache increment TTL must be at least one second.');
        }

        self::ensureDirectory();
        $file = self::file($key);
        $handle = fopen($file, 'c+');
        if ($handle === false) {
            throw new RuntimeException('Unable to open cache file.');
        }

        try {
            if (!flock($handle, LOCK_EX)) {
                throw new RuntimeException('Unable to lock cache file.');
            }

            rewind($handle);
            $raw = stream_get_contents($handle);
            $payload = is_string($raw) && $raw !== ''
                ? @unserialize($raw, ['allowed_classes' => false])
                : null;

            $now = time();
            $current = 0;
            $expiresAt = $now + $ttlSeconds;

            if (
                is_array($payload)
                && isset($payload['expires_at'])
                && (int) $payload['expires_at'] > $now
                && is_numeric($payload['value'] ?? null)
            ) {
                $current = (int) $payload['value'];
                $expiresAt = (int) $payload['expires_at'];
            }

            $current += $amount;
            $encoded = serialize(['expires_at' => $expiresAt, 'value' => $current]);

            rewind($handle);
            ftruncate($handle, 0);
            fwrite($handle, $encoded);
            fflush($handle);
            flock($handle, LOCK_UN);

            return $current;
        } finally {
            fclose($handle);
        }
    }

    /** @return array{0:bool,1:mixed} */
    private static function read(string $key): array
    {
        $file = self::file($key);
        if (!is_file($file)) {
            return [false, null];
        }

        $raw = @file_get_contents($file);
        $payload = is_string($raw)
            ? @unserialize($raw, ['allowed_classes' => false])
            : null;

        if (!is_array($payload) || !array_key_exists('expires_at', $payload) || !array_key_exists('value', $payload)) {
            @unlink($file);
            return [false, null];
        }

        $expiresAt = (int) $payload['expires_at'];
        if ($expiresAt !== 0 && $expiresAt <= time()) {
            @unlink($file);
            return [false, null];
        }

        return [true, $payload['value']];
    }

    /** @param array{expires_at:int,value:mixed} $payload */
    private static function writePayload(string $key, array $payload): void
    {
        $file = self::file($key);
        $temporary = $file . '.' . bin2hex(random_bytes(6)) . '.tmp';
        $bytes = @file_put_contents($temporary, serialize($payload), LOCK_EX);

        if ($bytes === false || !@rename($temporary, $file)) {
            @unlink($temporary);
            throw new RuntimeException('Unable to write cache file.');
        }
    }

    private static function ensureDirectory(): void
    {
        if (self::$directory === '') {
            throw new RuntimeException('Cache has not been configured.');
        }

        if (!is_dir(self::$directory) && !mkdir(self::$directory, 0775, true) && !is_dir(self::$directory)) {
            throw new RuntimeException('Unable to create cache directory.');
        }
    }

    private static function file(string $key): string
    {
        if (self::$directory === '') {
            throw new RuntimeException('Cache has not been configured.');
        }

        return self::$directory . DIRECTORY_SEPARATOR . self::$prefix . hash('sha256', $key) . '.cache';
    }
}
