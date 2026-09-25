<?php

declare(strict_types=1);

namespace SedoPHP\Core;

final class Config
{
    /** @var array<string, array<string, mixed>> */
    private static array $items = [];

    public static function load(string $directory, ?string $cacheFile = null): void
    {
        self::$items = [];

        if ($cacheFile !== null && is_file($cacheFile)) {
            $cached = require $cacheFile;
            if (!is_array($cached)) {
                throw new \RuntimeException("Invalid configuration cache: {$cacheFile}");
            }

            self::$items = $cached;
            return;
        }

        foreach (glob(rtrim($directory, '/') . '/*.php') ?: [] as $file) {
            $value = require $file;
            if (is_array($value)) {
                self::$items[basename($file, '.php')] = $value;
            }
        }
    }

    /** @return array<string,array<string,mixed>> */
    public static function all(): array
    {
        return self::$items;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $segments = explode('.', $key);
        $value = self::$items;

        foreach ($segments as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }

        return $value;
    }

    public static function set(string $key, mixed $value): void
    {
        $segments = explode('.', $key);
        $cursor =& self::$items;

        foreach ($segments as $segment) {
            if (!isset($cursor[$segment]) || !is_array($cursor[$segment])) {
                $cursor[$segment] = [];
            }
            $cursor =& $cursor[$segment];
        }

        $cursor = $value;
    }
}
