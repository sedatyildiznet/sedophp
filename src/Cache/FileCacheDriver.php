<?php

declare(strict_types=1);

namespace SedoPHP\Cache;

use RuntimeException;

final class FileCacheDriver implements CacheDriverInterface
{
    public function __construct(
        private readonly string $directory,
        private readonly string $prefix = 'sedo_',
    ) {
        if ($directory === '') {
            throw new RuntimeException('Cache directory cannot be empty.');
        }
    }

    public function get(string $key, mixed $default = null): mixed
    {
        [$found, $value] = $this->read($key);
        return $found ? $value : $default;
    }

    public function has(string $key): bool
    {
        return $this->read($key)[0];
    }

    public function put(string $key, mixed $value, ?int $ttlSeconds = null): void
    {
        if ($ttlSeconds !== null && $ttlSeconds < 1) {
            $this->forget($key);
            return;
        }

        $this->ensureDirectory();
        $expiresAt = $ttlSeconds === null ? 0 : time() + $ttlSeconds;
        $this->writePayload($key, ['expires_at' => $expiresAt, 'value' => $value]);
    }

    public function add(string $key, mixed $value, int $ttlSeconds): bool
    {
        if ($ttlSeconds < 1) {
            throw new RuntimeException('Cache add TTL must be at least one second.');
        }

        $this->ensureDirectory();
        $file = $this->file($key);

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

            [$found] = $this->read($key);
            if ($found) {
                return false;
            }
        }

        return false;
    }

    public function remember(string $key, int $ttlSeconds, callable $callback): mixed
    {
        [$found, $value] = $this->read($key);
        if ($found) {
            return $value;
        }

        $value = $callback();
        $this->put($key, $value, $ttlSeconds);
        return $value;
    }

    public function forget(string $key): void
    {
        $file = $this->file($key);
        if (is_file($file)) {
            @unlink($file);
        }
    }

    public function pull(string $key, mixed $default = null): mixed
    {
        $this->ensureDirectory();
        $file = $this->file($key);

        if (!is_file($file)) {
            return $default;
        }

        $handle = @fopen($file, 'c+');
        if ($handle === false) {
            return $default;
        }

        $value = $default;

        try {
            if (!flock($handle, LOCK_EX)) {
                throw new RuntimeException('Unable to lock cache file.');
            }

            rewind($handle);
            $raw = stream_get_contents($handle);
            $payload = is_string($raw) && $raw !== ''
                ? @unserialize($raw, ['allowed_classes' => false])
                : null;

            if (
                is_array($payload)
                && array_key_exists('expires_at', $payload)
                && array_key_exists('value', $payload)
            ) {
                $expiresAt = (int) $payload['expires_at'];
                if ($expiresAt === 0 || $expiresAt > time()) {
                    $value = $payload['value'];
                }
            }

            rewind($handle);
            ftruncate($handle, 0);
            fflush($handle);
            flock($handle, LOCK_UN);
        } finally {
            fclose($handle);
        }

        @unlink($file);
        return $value;
    }

    public function clear(): int
    {
        if (!is_dir($this->directory)) {
            return 0;
        }

        $count = 0;
        foreach (glob($this->directory . DIRECTORY_SEPARATOR . $this->prefix . '*.cache') ?: [] as $file) {
            if (is_file($file) && @unlink($file)) {
                $count++;
            }
        }
        return $count;
    }

    public function increment(string $key, int $amount = 1, int $ttlSeconds = 60): int
    {
        if ($ttlSeconds < 1) {
            throw new RuntimeException('Cache increment TTL must be at least one second.');
        }

        $this->ensureDirectory();
        $file = $this->file($key);
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
    private function read(string $key): array
    {
        $file = $this->file($key);
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
    private function writePayload(string $key, array $payload): void
    {
        $file = $this->file($key);
        $temporary = $file . '.' . bin2hex(random_bytes(6)) . '.tmp';
        $bytes = @file_put_contents($temporary, serialize($payload), LOCK_EX);

        if ($bytes === false || !@rename($temporary, $file)) {
            @unlink($temporary);
            throw new RuntimeException('Unable to write cache file.');
        }
    }

    private function ensureDirectory(): void
    {
        if (!is_dir($this->directory) && !mkdir($this->directory, 0775, true) && !is_dir($this->directory)) {
            throw new RuntimeException('Unable to create cache directory.');
        }
    }

    private function file(string $key): string
    {
        return $this->directory . DIRECTORY_SEPARATOR . $this->prefix . hash('sha256', $key) . '.cache';
    }
}
